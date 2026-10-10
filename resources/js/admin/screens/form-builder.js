import { api } from '../api.js'
import { FIELD_TYPES, blankField, hasChoices, isToggle, normaliseName, uniqueName } from '../lib/forms/field-types.js'
import { moveIn } from '../lib/sections/field-tree.js'

function blankForm() {
  return {
    id: null,
    name: '',
    slug: '',
    description: '',
    status: 'Active',
    submit_label: 'Submit',
    success_message: '',
    notify_email: '',
    store_submissions: true,
    fields: [],
    submissions_count: 0,
  }
}

/**
 * The form builder.
 *
 * Fields are edited client side and saved in one request, the same way the
 * section builder works — a half-saved form is one that rejects what visitors
 * send it. The preview is real DOM rather than an iframe, because the admin
 * panel loads the same stylesheet the public form uses, so what is on screen
 * here is literally the published markup.
 */
export default function formBuilder() {
  return {
    draft: blankForm(),
    options: { statuses: ['Active', 'Inactive'], field_types: Object.keys(FIELD_TYPES), widths: ['full', 'half'] },

    loading: true,
    saving: false,
    error: '',
    errors: {},
    dirty: false,

    tab: 'fields',
    activeIndex: 0,

    submissions: [],
    submissionsMeta: { current_page: 1, last_page: 1, total: 0, from: 0, to: 0 },
    submissionsLoading: false,
    submissionsLoaded: false,
    openSubmission: null,

    fieldTypes: FIELD_TYPES,

    async init() {
      const param = this.$store.ui.param

      try {
        this.options = await api.get('/forms/options')
      } catch {
        /* the builder falls back to its own lists */
      }

      if (param) await this.fetch(param)

      this.loading = false

      // Back / Forward between two forms keeps the builder mounted: load the
      // other form rather than go on editing the first.
      this.$watch('$store.ui.param', async (next) => {
        if (!next || Number(next) === this.draft.id) return
        this.loading = true
        await this.fetch(next)
        this.loading = false
      })
    },

    async fetch(id) {
      try {
        const payload = await api.get(`/forms/${id}`)
        this.adopt(payload.data)
      } catch (err) {
        this.error = err.message
      }
    },

    adopt(data) {
      this.draft = {
        ...blankForm(),
        ...data,
        fields: (data.fields ?? []).map((field) => ({ ...field, options: field.options ?? [] })),
      }

      this.activeIndex = Math.min(this.activeIndex, Math.max(0, this.draft.fields.length - 1))
      this.dirty = false
    },

    touch() {
      this.dirty = true
    },

    fieldError(key) {
      return this.errors?.[key]?.[0] || ''
    },

    // ── the field list ─────────────────────────────────────────────────
    get fields() {
      return this.draft.fields
    },

    get activeField() {
      return this.draft.fields[this.activeIndex] ?? null
    },

    selectField(index) {
      this.activeIndex = index
    },

    addField(type = 'text') {
      this.draft.fields.push(blankField(this.draft.fields, type))
      this.activeIndex = this.draft.fields.length - 1
      this.tab = 'fields'
      this.dirty = true
    },

    duplicateField(index) {
      const source = this.draft.fields[index]
      if (!source) return

      // Clearing the id makes the copy a new row rather than a rename.
      const copy = JSON.parse(JSON.stringify(source))
      copy.id = null
      copy.label = `${source.label} copy`
      copy.name = uniqueName(this.draft.fields, copy.label)

      this.draft.fields.splice(index + 1, 0, copy)
      this.activeIndex = index + 1
      this.dirty = true
    },

    removeField(index) {
      this.draft.fields.splice(index, 1)
      this.activeIndex = Math.max(0, Math.min(this.activeIndex, this.draft.fields.length - 1))
      this.dirty = true
    },

    moveField(index, direction) {
      if (!moveIn(this.draft.fields, index, direction)) return

      this.activeIndex = index + direction
      this.dirty = true
    },

    /**
     * The name is the key every stored submission uses, so it follows the
     * label only while nobody has set one by hand — renaming a live field
     * would orphan the answers already collected under the old key.
     */
    onLabelInput(field) {
      if (!field.id && !field.nameTouched) {
        field.name = uniqueName(this.draft.fields, field.label, field)
      }

      this.dirty = true
    },

    onNameInput(field) {
      field.nameTouched = true
      field.name = normaliseName(field.name)
      this.dirty = true
    },

    onNameBlur(field) {
      field.name = uniqueName(this.draft.fields, field.name || field.label, field)
      this.dirty = true
    },

    onTypeChange(field) {
      if (hasChoices(field) && (field.options ?? []).length === 0 && field.type !== 'checkbox') {
        field.options = ['First option', 'Second option']
      }

      this.dirty = true
    },

    hasChoices(field) {
      return hasChoices(field)
    },

    isToggle(field) {
      return isToggle(field)
    },

    takesPlaceholder(field) {
      return Boolean(FIELD_TYPES[field?.type]?.placeholder)
    },

    fieldSummary(field) {
      const type = FIELD_TYPES[field.type]?.label ?? field.type
      return `${type}${field.required ? ' · required' : ''}${field.width === 'half' ? ' · half' : ''}`
    },

    // ── options on a choice field ──────────────────────────────────────
    addOption(field) {
      field.options.push('')
      this.dirty = true
    },

    removeOption(field, index) {
      field.options.splice(index, 1)
      this.dirty = true
    },

    moveOption(field, index, direction) {
      if (moveIn(field.options, index, direction)) this.dirty = true
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save() {
      if (this.saving) return

      this.saving = true
      this.error = ''
      this.errors = {}

      const payload = {
        name: this.draft.name,
        slug: this.draft.slug,
        description: this.draft.description,
        status: this.draft.status,
        submit_label: this.draft.submit_label,
        success_message: this.draft.success_message,
        notify_email: this.draft.notify_email,
        store_submissions: this.draft.store_submissions,
        fields: this.draft.fields.map((field, position) => ({
          id: field.id,
          type: field.type,
          label: field.label,
          name: field.name,
          placeholder: field.placeholder,
          help: field.help,
          width: field.width,
          rule: field.rule,
          required: field.required,
          options: field.options,
          position,
        })),
      }

      try {
        const result = this.draft.id
          ? await api.put(`/forms/${this.draft.id}`, payload)
          : await api.post('/forms', payload)

        this.adopt(result.data)
        this.$store.ui.go('forms', this.draft.id)
        this.$store.ui.notify('Form saved')
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message

        // A field-level message is invisible from the Settings tab.
        if (Object.keys(this.errors).some((key) => key.startsWith('fields.'))) this.tab = 'fields'
      } finally {
        this.saving = false
      }
    },

    /** The first complaint about any field, shown on its row in the list. */
    errorForField(index) {
      const key = Object.keys(this.errors).find((k) => k.startsWith(`fields.${index}.`))
      return key ? this.errors[key][0] : ''
    },

    // ── submissions ────────────────────────────────────────────────────
    async openSubmissions() {
      this.tab = 'submissions'
      if (!this.submissionsLoaded) await this.loadSubmissions()
    },

    async loadSubmissions(page = 1) {
      if (!this.draft.id) return

      this.submissionsLoading = true

      try {
        const payload = await api.get(`/forms/${this.draft.id}/submissions?page=${page}&per_page=20`)
        this.submissions = payload.data
        this.submissionsMeta = payload.meta
        this.submissionsLoaded = true
      } catch (err) {
        this.error = err.message
      } finally {
        this.submissionsLoading = false
      }
    },

    async deleteSubmission(row) {
      const confirmed = await this.$store.dialog.confirm({
        title: 'Delete submission',
        message: 'Delete this submission? This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
      })
      if (!confirmed) return

      try {
        await api.delete(`/forms/${this.draft.id}/submissions/${row.id}`)
        this.openSubmission = null
        this.draft.submissions_count = Math.max(0, (this.draft.submissions_count ?? 1) - 1)
        await this.loadSubmissions(this.submissionsMeta.current_page)
        this.$store.ui.notify('Submission deleted')
      } catch (err) {
        this.error = err.message
      }
    },

    get exportUrl() {
      return `/admin/api/forms/${this.draft.id}/submissions/export`
    },

    // ── the public address ─────────────────────────────────────────────
    get publicNote() {
      return this.draft.slug
        ? `Posts to /forms/${this.draft.slug}`
        : 'The address is built from the name when you save.'
    },

    backToList() {
      this.$store.ui.go('forms')
    },
  }
}
