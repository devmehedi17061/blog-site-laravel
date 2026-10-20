import { api } from '../api.js'
import { copyText } from '../lib/clipboard.js'
import { FIELD_TYPES, groupTokens, normaliseKey, tokenFor } from '../lib/sections/template.js'
import { moveIn, newField, uniqueKey } from '../lib/sections/fieldTree.js'
import { previewDoc } from '../lib/sections/preview.js'
import { insertAtCursor } from '../lib/codeEditor.js'

const STARTER_HTML = [
  '<div class="block">',
  '  <h2>{{ heading }}</h2>',
  '  <div class="block-body">{{ body }}</div>',
  '</div>',
].join('\n')

const STARTER_CSS = [
  '.block { max-width: 760px; }',
  '.block h2 { margin: 0 0 12px; font-size: 30px; }',
  '.block-body { font-size: 16px; line-height: 1.7; color: #605d5d; }',
].join('\n')

function blankTemplate() {
  return {
    id: null,
    name: 'New section',
    description: '',
    html: STARTER_HTML,
    css: STARTER_CSS,
    fields: [
      { key: 'heading', label: 'Heading', type: 'text' },
      { key: 'body', label: 'Paragraph', type: 'richtext' },
    ],
  }
}

/** A grey rectangle, so a media field shows where its image will sit. */
const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,'
  + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400">'
    + '<rect width="640" height="400" fill="#eceaea"/>'
    + '<text x="320" y="210" font-family="sans-serif" font-size="22" fill="#9a9595" text-anchor="middle">Image</text>'
    + '</svg>'
  )

/**
 * The section library: build a section once, then use it on any number of
 * pages. Only the shape lives here — HTML, CSS and the fields. What each page
 * writes into those fields is the page's own business.
 */
export default function sectionLibrary() {
  return {
    templates: [],
    draft: null,
    search: '',

    loading: true,
    saving: false,
    error: '',
    errors: {},
    dirty: false,
    pane: 'fields',

    fieldTypes: FIELD_TYPES,

    async init() {
      await this.load()

      const wanted = this.routed()

      if (wanted) this.select(wanted)
      else if (this.templates.length) this.select(this.templates[0])
      else this.startNew()

      // The screen stays mounted while the hash moves — back, forward, or a
      // link from a page's section — so the route is followed, not just read.
      this.$watch('$store.ui.param', () => this.openFromRoute())
    },

    /** The template the current url names, if any. */
    routed() {
      const param = this.$store.ui.param
      if (!param) return null

      return this.templates.find((t) => String(t.id) === String(param)) ?? null
    },

    openFromRoute() {
      const wanted = this.routed()

      // No id in the url, or the one already open: nothing to do. The second
      // case is what keeps select() from looping through its own go().
      if (!wanted || wanted.id === this.draft?.id) return

      this.select(wanted)
    },

    // ── list ───────────────────────────────────────────────────────────
    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get('/section-templates')
        this.templates = payload.data
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    get visibleTemplates() {
      const term = this.search.trim().toLowerCase()
      if (!term) return this.templates

      return this.templates.filter(
        (t) => t.name.toLowerCase().includes(term) || (t.description ?? '').toLowerCase().includes(term)
      )
    },

    async select(template) {
      if (!(await this.confirmDiscard())) return

      // A deep copy, so editing never touches the row in the list until a save.
      this.draft = JSON.parse(JSON.stringify(template))
      this.draft.fields = this.draft.fields ?? []
      // Every card starts folded: the list reads as an outline of the
      // section, and one click opens the field to edit.
      this.collapsed = [...this.fields]
      this.errors = {}
      this.dirty = false
      this.pane = 'fields'

      if (template.id) this.$store.ui.go('sections', template.id)
    },

    async startNew() {
      if (!(await this.confirmDiscard())) return

      this.draft = blankTemplate()
      this.collapsed = []
      this.errors = {}
      this.dirty = false
      this.pane = 'fields'
      this.$store.ui.go('sections')
    },

    async confirmDiscard() {
      if (!this.dirty) return true

      return this.$store.dialog.confirm({
        title: 'Discard changes',
        message: 'This section has unsaved changes. Leaving it will throw them away.',
        confirmLabel: 'Discard changes',
        cancelLabel: 'Keep editing',
        danger: true,
      })
    },

    isActive(template) {
      return this.draft?.id === template.id
    },

    usageLabel(template) {
      const used = template.used_by ?? 0
      return used === 1 ? 'on 1 page section' : `on ${used} page sections`
    },

    touch() {
      this.dirty = true
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save() {
      if (!this.draft || this.saving) return

      this.saving = true
      this.error = ''
      this.errors = {}

      try {
        const payload = {
          name: this.draft.name,
          description: this.draft.description,
          html: this.draft.html,
          css: this.draft.css,
          fields: this.draft.fields,
        }

        const result = this.draft.id
          ? await api.put(`/section-templates/${this.draft.id}`, payload)
          : await api.post('/section-templates', payload)

        // The saved copy is new objects: fold the same fields again, by key,
        // so saving does not spring every card open.
        const folded = this.collapsed.map((f) => f.key)
        this.draft = { ...result.data }
        this.collapsed = (this.draft.fields ?? []).filter((f) => folded.includes(f.key))
        this.dirty = false

        await this.load()
        this.$store.ui.go('sections', this.draft.id)
        this.$store.ui.notify('Section saved')
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message

        // Open any folded card holding an error ("fields.2.key"), or it hides.
        const failing = new Set(Object.keys(this.errors)
          .map((key) => key.match(/^fields\.(\d+)\./)?.[1])
          .filter(Boolean)
          .map(Number))
        this.collapsed = this.collapsed.filter((f) => !failing.has(this.fields.indexOf(f)))
      } finally {
        this.saving = false
      }
    },

    async remove(template) {
      const used = template.used_by ?? 0
      const warning = used > 0
        ? `"${template.name}" is used by ${used} page section(s). They will have nothing to render. Delete it?`
        : `Delete "${template.name}"?`

      const confirmed = await this.$store.dialog.confirm({
        title: 'Delete section',
        message: warning,
        confirmLabel: 'Delete section',
        danger: true,
      })
      if (!confirmed) return

      try {
        const result = await api.delete(`/section-templates/${template.id}`)
        await this.load()

        if (this.draft?.id === template.id) {
          this.dirty = false
          this.templates.length ? this.select(this.templates[0]) : this.startNew()
        }

        this.$store.ui.notify(result.message ?? 'Section deleted')
      } catch (err) {
        this.error = err.message
      }
    },

    async duplicate(template) {
      if (!(await this.confirmDiscard())) return

      const copy = JSON.parse(JSON.stringify(template))
      copy.id = null
      copy.slug = null
      copy.name = `${template.name} copy`

      this.draft = copy
      this.collapsed = [...this.fields]
      this.dirty = true
      this.pane = 'fields'
      this.$store.ui.go('sections')
    },

    fieldError(key) {
      return this.errors?.[key]?.[0] || ''
    },

    // ── field tree ─────────────────────────────────────────────────────
    get fields() {
      return this.draft?.fields ?? []
    },

    addField(type = 'text') {
      if (!this.draft) return

      this.draft.fields.push(newField(this.draft.fields, type))
      this.dirty = true
    },

    removeField(index) {
      const [gone] = this.draft?.fields.splice(index, 1) ?? []
      this.collapsed = this.collapsed.filter((f) => f !== gone)
      this.dirty = true
    },

    // ── collapsing field cards ─────────────────────────────────────────
    // Held here, by field object, never on the field itself — so it is not
    // saved into the template and does not make the section look edited.
    collapsed: [],

    isCollapsed(field) {
      return this.collapsed.includes(field)
    },

    toggleField(field) {
      this.collapsed = this.isCollapsed(field)
        ? this.collapsed.filter((f) => f !== field)
        : [...this.collapsed, field]
    },

    collapseAll() {
      this.collapsed = [...this.fields]
    },

    expandAll() {
      this.collapsed = []
    },

    get allCollapsed() {
      return this.fields.length > 0 && this.fields.every((f) => this.isCollapsed(f))
    },

    /** One line for a folded card: what it is without opening it. */
    fieldSummary(field) {
      const type = this.fieldTypes[field.type]?.label ?? field.type
      const inside = field.type === 'group' ? ` · ${field.fields?.length ?? 0} field(s) inside` : ''
      return `${field.label || field.key || 'Untitled'} · ${type}${inside}`
    },

    moveField(index, direction) {
      if (moveIn(this.draft?.fields, index, direction)) this.dirty = true
    },

    addSubField(group) {
      group.fields = group.fields ?? []
      group.fields.push(newField(group.fields))
      this.dirty = true
    },

    removeSubField(group, index) {
      group.fields.splice(index, 1)
      this.dirty = true
    },

    moveSubField(group, index, direction) {
      if (moveIn(group.fields, index, direction)) this.dirty = true
    },

    /** Keys are tokens, so they are normalised as the author types. */
    onKeyInput(field) {
      field.key = normaliseKey(field.key)
      this.dirty = true
    },

    /** De-duplicated on the way out, so two fields never share a token. */
    onKeyBlur(field, siblings) {
      field.key = uniqueKey(siblings, field.key || 'field', field)
      this.dirty = true
    },

    onTypeChange(field) {
      if (field.type === 'group' && !field.fields?.length) {
        field.fields = [{ key: 'title', label: 'Title', type: 'text' }]
      }

      this.dirty = true
    },

    /** Types a sub-field may use — groups nest one level only. */
    get subFieldTypes() {
      return Object.keys(FIELD_TYPES).filter((type) => type !== 'group')
    },

    get allFieldTypes() {
      return Object.keys(FIELD_TYPES)
    },

    // ── tokens ─────────────────────────────────────────────────────────
    tokenFor(key) {
      return tokenFor(key)
    },

    groupOpen(key) {
      return groupTokens(key).start
    },

    groupClose(key) {
      return groupTokens(key).end
    },

    /**
     * What typing {{ offers in the code editors: this section's fields (a
     * group as its repeat wrapper), then the tokens every section gets.
     */
    get codeTokens() {
      const fields = this.fields.filter((f) => f.key).flatMap((field) => [
        { key: field.key, detail: field.type === 'group' ? 'repeat' : (field.label || field.type), group: field.type === 'group' },
        ...(field.type === 'group' ? (field.fields ?? []).filter((s) => s.key).map((s) => ({ key: s.key, detail: `in ${field.key}` })) : []),
      ])
      const extra = [...this.pageTokens, '_number', '_index', '_count']
        .filter((key) => !fields.some((f) => f.key === key))
        .map((key) => ({ key, detail: key.startsWith('_') ? 'in a group' : 'from the page' }))
      const lists = fields.some((f) => f.key === 'latest_posts')
        ? []
        : [
            { key: 'latest_posts', detail: 'newest posts', group: true },
            ...this.postTokens.map((key) => ({ key, detail: 'in latest_posts' })),
          ]

      return [...fields, ...extra, ...lists]
    },

    /** A token button: into the HTML where the cursor is (copied if the editor is not there). */
    async insertCode(text) {
      if (this.pane !== 'html') this.pane = 'html'
      await this.$nextTick()
      if (!insertAtCursor(this.$refs.html, text)) await this.copy(text)
    },

    async copy(text) {
      await copyText(text)
      this.$store.ui.notify(`Copied ${text}`)
    },

    /**
     * Tokens every section gets for free. `_index` and `_number` are only
     * meaningful inside a group, so they are listed with the group rather
     * than here.
     */
    pageTokens: ['page_title', 'page_url'],

    /**
     * {{#latest_posts}} repeats the newest published posts; these are each
     * post's tokens inside it (App\Support\LatestPosts). A posts_count field
     * sets how many, a fallback_image field the picture for a post with none.
     */
    postTokens: ['title', 'url', 'date', 'category', 'excerpt', 'image'],

    /** Tokens used in the HTML that no field backs. */
    get unknownTokens() {
      if (!this.draft) return []

      const known = new Set([...this.pageTokens, '_index', '_number', '_count', 'latest_posts', ...this.postTokens])

      for (const field of this.fields) {
        known.add(field.key)
        for (const sub of field.fields ?? []) known.add(sub.key)
      }

      const used = [...String(this.draft.html || '').matchAll(/\{\{\s*[#/]?\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/g)]
        .map((match) => match[1])

      return [...new Set(used.filter((key) => !known.has(key)))]
    },

    /**
     * How many times the HTML and CSS mention a field's token.
     *
     * A group is counted by its opening tag only — `{{#cards}}` and
     * `{{/cards}}` are one use, not two.
     */
    tokenUses(field) {
      if (!field?.key || !this.draft) return 0

      const source = String(this.draft.html || '') + String(this.draft.css || '')

      const pattern = field.type === 'group'
        ? `\\{\\{#\\s*${field.key}\\s*\\}\\}`
        : `\\{\\{\\s*${field.key}\\s*\\}\\}`

      return (source.match(new RegExp(pattern, 'g')) ?? []).length
    },

    /** Fields the HTML never mentions, which would never reach a page. */
    get unusedFields() {
      return this.fields.filter((field) => field.key && this.tokenUses(field) === 0).map((f) => f.key)
    },

    /**
     * Fields whose token appears more than once.
     *
     * Legitimate for a heading you want twice — but for a media or form
     * field it almost always means a second slot was pasted without its
     * token being changed, so both slots show the same thing. That is worth
     * saying out loud rather than leaving someone to find it on the site.
     */
    get repeatedFields() {
      return this.fields
        .filter((field) => field.key && this.tokenUses(field) > 1)
        .map((field) => ({ key: field.key, type: field.type, uses: this.tokenUses(field) }))
    },

    /** The repeats that are almost certainly a mistake. */
    get suspiciousRepeats() {
      return this.repeatedFields.filter((field) => ['form', 'media'].includes(field.type))
    },

    fieldWarning(field) {
      if (!field?.key) return ''

      const uses = this.tokenUses(field)

      if (uses === 0) return 'Not used in the HTML — nothing a page types here will show.'
      if (uses > 1 && ['form', 'media'].includes(field.type)) {
        return `Used ${uses} times — every slot will show the same one.`
      }
      if (uses > 1) return `Used ${uses} times.`

      return ''
    },

    // ── preview ────────────────────────────────────────────────────────
    previewDevices: {
      // The column's own width: readable, no scaling.
      fit: { label: 'Fit', width: 0 },
      desktop: { label: 'Desktop', width: 1280 },
      tablet: { label: 'Tablet', width: 820 },
      mobile: { label: 'Phone', width: 390 },
    },
    previewDevice: 'fit',
    previewLarge: false,
    previewStageWidth: 0,
    previewStageHeight: 0,

    /** The frame is scaled to the column, so it tracks the column's size. */
    watchPreviewStage() {
      const stage = this.$refs.previewStage
      if (!stage || this._previewObserver) return
      this._previewObserver = new ResizeObserver(() => {
        this.previewStageWidth = stage.clientWidth
        this.previewStageHeight = stage.clientHeight
      })
      this._previewObserver.observe(stage)
    },

    /** The width the frame renders at: a device's, or the column's for Fit. */
    get previewWidth() {
      return this.previewDevices[this.previewDevice].width || Math.max(320, this.previewStageWidth)
    },

    get previewScale() {
      return this.previewStageWidth ? Math.min(1, this.previewStageWidth / this.previewWidth) : 1
    },

    togglePreviewLarge() {
      this.previewLarge = !this.previewLarge
    },

    get previewFrameStyle() {
      const width = this.previewWidth
      const scale = this.previewScale
      const height = Math.max(420, this.previewStageHeight) / scale
      // Centred when it is narrower than the column (a phone), flush otherwise.
      const left = Math.max(0, (this.previewStageWidth - width * scale) / 2)
      return `width:${width}px;height:${Math.round(height)}px;left:${Math.round(left)}px;transform:scale(${scale});`
    },

    /** Stand-in values, so the preview shows the shape before a page fills it. */
    get sampleValues() {
      const sample = (fields) => {
        const out = {}

        for (const field of fields ?? []) {
          if (!field.key) continue

          // A default is what a page starts with — the truest stand-in.
          if (field.default) out[field.key] = field.default
          else if (field.type === 'media') out[field.key] = PLACEHOLDER_IMAGE
          else if (field.type === 'url') out[field.key] = '#'
          else if (field.type === 'richtext') out[field.key] = `<p>${field.label || field.key} text goes here.</p>`
          else if (field.type === 'select') out[field.key] = String(field.options || 'yes').split(',')[0].trim()
          else out[field.key] = field.label || field.key
        }

        return out
      }

      const values = sample(this.fields.filter((f) => f.type !== 'group'))

      for (const group of this.fields.filter((f) => f.type === 'group')) {
        const item = sample(group.fields)
        // Its sample items when it has them (a slider's slides), else three alike.
        values[group.key] = Array.isArray(group.items) && group.items.length
          ? group.items.map((one) => ({ ...item, ...Object.fromEntries(Object.entries(one).filter(([, v]) => v !== '')) }))
          : [item, { ...item }, { ...item }]
      }

      return values
    },

    get previewDoc() {
      if (!this.draft) return ''

      return previewDoc({
        mode: 'Library',
        html: this.draft.html,
        css: this.draft.css,
        fields: this.fields,
        values: this.sampleValues,
        style: {},
        // Stood in for here too: the real ones arrive from whichever page
        // the section ends up on.
        globals: { page_title: 'This page', page_url: '/this-page' },
        // Spacing a section usually gets on a page; each page sets its own.
        frame: { padding: '48px 32px', max_width: 'var(--site-width)' },
      })
    },
  }
}
