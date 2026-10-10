import { api } from '../../api.js'
import { FIELD_TYPES, blankItem, blankValues, escapeHtml, renderSection } from './template.js'
import { moveIn } from './field-tree.js'
import { BACKGROUND_IMAGE_FIELDS, BAND_STYLE_FIELDS, CONTENT_STYLE_FIELDS } from './style.js'
import { previewDoc } from './preview.js'

const BLANK_HTML = [
  '<div class="block">',
  '  <h2>Section heading</h2>',
  '  <p>Markup written for this one page.</p>',
  '</div>',
].join('\n')

const BLANK_CSS = [
  '.block { max-width: 760px; }',
  '.block h2 { margin: 0 0 12px; font-size: 30px; }',
  '.block p { margin: 0; font-size: 16px; line-height: 1.7; color: #605d5d; }',
].join('\n')

/**
 * A page's list of sections.
 *
 * There are two kinds. A **library** section points at a row in the section
 * library and stores only the values typed into that section's fields, so
 * editing the library reaches every page while each page keeps its own words.
 * A **blank** section is HTML and CSS written for this page alone.
 *
 * The whole list is edited client side and saved in one request, so every
 * change just marks it dirty.
 */
export function pageSections() {
  return {
    sections: [],
    activeIndex: 0,

    library: [],
    libraryOpen: false,
    librarySearch: '',
    libraryLoaded: false,

    formsList: [],
    formsLoaded: false,

    sectionsLoading: false,
    sectionsSaving: false,
    sectionsError: '',
    sectionErrors: {},
    dirty: false,
    pane: 'content',

    fieldTypes: FIELD_TYPES,
    bandStyleFields: BAND_STYLE_FIELDS,
    backgroundImageFields: BACKGROUND_IMAGE_FIELDS,
    contentStyleFields: CONTENT_STYLE_FIELDS,

    // ── loading and saving ─────────────────────────────────────────────
    async loadSections(pageId) {
      if (!pageId) return

      this.sectionsLoading = true
      this.sectionsError = ''

      try {
        const payload = await api.get(`/pages/${pageId}/sections`)
        this.adoptSections(payload.data)
      } catch (err) {
        this.sectionsError = err.message
      } finally {
        this.sectionsLoading = false
      }
    },

    async loadLibrary() {
      if (this.libraryLoaded) return

      try {
        const payload = await api.get('/section-templates')
        this.library = payload.data
        this.libraryLoaded = true
      } catch (err) {
        this.sectionsError = err.message
      }
    },

    /** Takes the server's list and makes every section safe to bind to. */
    adoptSections(rows) {
      this.sections = (rows ?? []).map((section) => this.normalise(section))
      this.activeIndex = Math.min(this.activeIndex, Math.max(0, this.sections.length - 1))
      this.dirty = false

      // Only fetched when something on this page actually needs the list.
      if (this.sections.some((s) => (s.template?.fields ?? []).some((f) => f.type === 'form'))) {
        this.loadForms()
      }
    },

    /** The forms a `form` field can point at. */
    async loadForms() {
      if (this.formsLoaded) return

      try {
        const payload = await api.get('/forms?per_page=100&status=Active')
        this.formsList = payload.data
        this.formsLoaded = true
        this.resolveFormSlugs()
      } catch {
        /* the field falls back to a plain box holding the slug */
      }
    },

    /**
     * A starter page names its form by slug, because the seeder runs before
     * any id exists. The picker works in ids, so a slug is swapped for one as
     * soon as the list is known — without marking the page dirty, since
     * nothing the author chose has changed.
     */
    resolveFormSlugs() {
      for (const section of this.sections) {
        for (const field of section.template?.fields ?? []) {
          if (field.type !== 'form') continue

          const current = section.values[field.key]
          if (!current || /^\d+$/.test(String(current))) continue

          const match = this.formsList.find((form) => form.slug === current)
          if (match) section.values[field.key] = String(match.id)
        }
      }
    },

    /**
     * Fills in anything a binding will reach for. The library may have gained
     * a field since this page was last saved, and a value that should be a
     * repeatable list may be missing entirely — x-for would break on either.
     */
    normalise(section) {
      const copy = {
        ...section,
        values: { ...(section.values ?? {}) },
        style: { ...(section.style ?? {}) },
      }

      for (const field of copy.template?.fields ?? []) {
        if (!field.key) continue

        if (field.type === 'group') {
          const items = Array.isArray(copy.values[field.key]) ? copy.values[field.key] : []
          copy.values[field.key] = items.map((item) => ({ ...blankItem(field.fields), ...(item ?? {}) }))
          continue
        }

        if (copy.values[field.key] === undefined || copy.values[field.key] === null) {
          copy.values[field.key] = ''
        }
      }

      return copy
    },

    async saveSections(pageId) {
      if (!pageId || this.sectionsSaving) return

      this.sectionsSaving = true
      this.sectionsError = ''
      this.sectionErrors = {}

      try {
        const payload = await api.put(`/pages/${pageId}/sections`, { sections: this.sections })
        this.adoptSections(payload.data)
        this.$store.ui.notify('Sections saved')
      } catch (err) {
        this.sectionErrors = err.errors || {}
        this.sectionsError = Object.keys(this.sectionErrors).length
          ? 'Some sections need attention.'
          : err.message
      } finally {
        this.sectionsSaving = false
      }
    },

    sectionError(index, field) {
      return this.sectionErrors?.[`sections.${index}.${field}`]?.[0] || ''
    },

    touch() {
      this.dirty = true
    },

    // ── the list ───────────────────────────────────────────────────────
    get activeSection() {
      return this.sections[this.activeIndex] ?? null
    },

    get isLibrarySection() {
      return this.activeSection?.mode === 'Library'
    },

    /** The field tree this section is filling in, straight from the library. */
    get activeFields() {
      return this.activeSection?.template?.fields ?? []
    },

    selectSection(index) {
      this.activeIndex = index
      this.pane = this.sections[index]?.mode === 'Blank' && this.pane === 'content' ? 'html' : this.pane
    },

    sectionMeta(section) {
      if (section.mode === 'Blank') return 'Blank · HTML + CSS'

      const name = section.template?.name
      return name ? `Library · ${name}` : 'Library · section missing'
    },

    // ── adding ─────────────────────────────────────────────────────────
    async openLibrary() {
      this.librarySearch = ''
      this.libraryOpen = true
      await this.loadLibrary()
    },

    get visibleLibrary() {
      const term = this.librarySearch.trim().toLowerCase()
      if (!term) return this.library

      return this.library.filter(
        (t) => t.name.toLowerCase().includes(term) || (t.description ?? '').toLowerCase().includes(term)
      )
    },

    /** Adds a library section: its shape is shared, its values are ours. */
    addFromLibrary(template) {
      // The page may not have needed the form list until this moment.
      if ((template.fields ?? []).some((field) => field.type === 'form')) {
        this.loadForms()
      }

      this.sections.push(
        this.normalise({
          id: null,
          name: template.name,
          mode: 'Library',
          template_id: template.id,
          template: {
            id: template.id,
            name: template.name,
            fields: template.fields ?? [],
            html: template.html,
            css: template.css,
          },
          html: null,
          css: null,
          values: blankValues(template.fields),
          style: {},
        })
      )

      this.activeIndex = this.sections.length - 1
      this.pane = 'content'
      this.libraryOpen = false
      this.dirty = true
    },

    addBlankSection() {
      this.sections.push(
        this.normalise({
          id: null,
          name: 'Blank section',
          mode: 'Blank',
          template_id: null,
          template: null,
          html: BLANK_HTML,
          css: BLANK_CSS,
          values: {},
          style: {},
        })
      )

      this.activeIndex = this.sections.length - 1
      this.pane = 'html'
      this.dirty = true
    },

    duplicateSection(index) {
      const source = this.sections[index]
      if (!source) return

      // Clearing the id makes the copy a new row rather than a rename.
      const copy = JSON.parse(JSON.stringify(source))
      copy.id = null
      copy.name = `${source.name} copy`

      this.sections.splice(index + 1, 0, copy)
      this.activeIndex = index + 1
      this.dirty = true
    },

    removeSection(index) {
      this.sections.splice(index, 1)
      this.activeIndex = Math.max(0, Math.min(this.activeIndex, this.sections.length - 1))
      this.dirty = true
    },

    moveSection(index, direction) {
      if (!moveIn(this.sections, index, direction)) return

      this.activeIndex = index + direction
      this.dirty = true
    },

    // ── repeatable groups ──────────────────────────────────────────────
    addItem(section, field) {
      section.values[field.key].push(blankItem(field.fields))
      this.dirty = true
    },

    duplicateItem(section, field, index) {
      const source = section.values[field.key][index]
      section.values[field.key].splice(index + 1, 0, { ...source })
      this.dirty = true
    },

    removeItem(section, field, index) {
      section.values[field.key].splice(index, 1)
      this.dirty = true
    },

    moveItem(section, field, index, direction) {
      if (moveIn(section.values[field.key], index, direction)) this.dirty = true
    },

    itemLabel(field, item, index) {
      const first = (field.fields ?? []).find((sub) => sub.type === 'text')
      const text = first ? String(item[first.key] ?? '').trim() : ''

      return text || `Item ${index + 1}`
    },

    /**
     * Writes one value. The rich text editor calls this rather than binding,
     * because it owns its own document and only reports finished HTML.
     */
    setValue(target, key, value) {
      target[key] = value
      this.dirty = true
    },

    /** What a library card says about the section's shape. */
    fieldSummary(template) {
      const fields = template.fields ?? []
      const groups = fields.filter((field) => field.type === 'group').length
      const plain = fields.length - groups

      const parts = []
      if (plain) parts.push(`${plain} field${plain === 1 ? '' : 's'}`)
      if (groups) parts.push(`${groups} repeatable group${groups === 1 ? '' : 's'}`)

      return parts.length ? parts.join(' · ') : 'No fields'
    },

    // ── media ──────────────────────────────────────────────────────────
    async pickMedia(target, key) {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose an image' })
      if (!media) return

      target[key] = media.url
      this.dirty = true
    },

    clearMedia(target, key) {
      target[key] = ''
      this.dirty = true
    },

    // ── preview ────────────────────────────────────────────────────────
    /**
     * A form's markup is built on the server from its field definitions, so
     * the browser cannot draw the real thing here. It previews as a named box
     * in the right place instead, which is what the layout needs to show.
     *
     * The swap is done on copies: the field is retyped to `richtext` so the
     * placeholder goes in as markup rather than escaped text.
     */
    get previewShape() {
      const defined = this.activeSection?.template?.fields ?? []

      if (!defined.some((field) => field.type === 'form')) {
        return { fields: defined, values: this.activeSection?.values ?? {} }
      }

      const values = { ...(this.activeSection?.values ?? {}) }

      for (const field of defined) {
        if (field.type !== 'form') continue

        const chosen = this.formsList.find(
          (form) => String(form.id) === String(values[field.key]) || form.slug === values[field.key]
        )

        const label = chosen ? `${chosen.name} form` : 'No form chosen'

        values[field.key] = '<div style="padding:22px;text-align:center;border:1px dashed #b9b4b4;'
          + 'background:#f7f5f5;color:#605d5d;font-size:13px">'
          + escapeHtml(label)
          + '</div>'
      }

      return {
        fields: defined.map((field) => (field.type === 'form' ? { ...field, type: 'richtext' } : field)),
        values,
      }
    },

    get previewDoc() {
      const section = this.activeSection
      if (!section) return ''

      const library = section.mode === 'Library'
      const shape = this.previewShape

      return previewDoc({
        mode: section.mode,
        html: library ? (section.template?.html ?? '') : section.html,
        css: library ? (section.template?.css ?? '') : section.css,
        fields: shape.fields,
        values: shape.values,
        style: section.style,
        globals: this.pageTokens,
      })
    },

    /**
     * What the page contributes to every section it holds, so a breadcrumb
     * previews with this page's real title rather than a placeholder.
     */
    get pageTokens() {
      return {
        page_title: escapeHtml(this.draft?.title ?? ''),
        page_url: escapeHtml(this.storedSlug ?? ''),
      }
    },

    /** What the page will publish, for the SEO reader to look at. */
    get sectionsAsHtml() {
      return this.sections.map((section) => renderSection(section, this.pageTokens)).join('')
    },
  }
}
