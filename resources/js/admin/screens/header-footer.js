import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'
import { extend } from '../lib/extend.js'
import { tokenFor } from '../lib/sections/template.js'
import sectionLibrary from './section-library.js'
import { insertAtCursor } from '../lib/codeEditor.js'

/**
 * Builders → Header & footer.
 *
 * Built the way the Section library is — HTML, CSS and fields — because it
 * *is* the section library underneath: the field editor, the token checks
 * and the fold-up cards all come from sectionLibrary(). What differs:
 *
 *   - headers and footers hold their own values (the Content tab), since the
 *     whole site shares one; there is no page to fill them in
 *   - site tokens — logo, menus, search box, ☰ button, social, contact —
 *     are inserted with one click
 *   - the preview is rendered by the server with the real menus and logo,
 *     at desktop, tablet and phone width
 *   - one header and one footer are "in use" on the site
 */

const DEVICES = {
  desktop: { label: 'Desktop', width: 1280 },
  tablet: { label: 'Tablet', width: 820 },
  mobile: { label: 'Phone', width: 390 },
}

const t = tokenFor

const STARTERS = {
  header: {
    html: [
      '<div class="bar">',
      '  <div class="wrap">',
      `    ${t('site_brand')}`,
      '    <div class="nav" data-menu-panel>',
      `      ${t('menu_header')}`,
      `      ${t('search')}`,
      '    </div>',
      `    ${t('menu_toggle')}`,
      '  </div>',
      '</div>',
    ].join('\n'),
    css: [
      '.bar { background: #ffffff; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }',
      '.wrap { display: flex; align-items: center; gap: 20px; max-width: var(--site-width); margin: 0 auto; padding: 14px 16px; }',
      '.nav { display: flex; align-items: center; gap: 16px; margin-left: auto; }',
      '.menu-toggle { display: none; }',
      '',
      '/* Tablets and phones: the menu folds behind the ☰ button. */',
      '@media (max-width: 900px) {',
      '  .wrap { flex-wrap: wrap; }',
      '  .menu-toggle { display: grid; margin-left: auto; order: 2; }',
      '  .nav { order: 3; display: none; width: 100%; flex-direction: column; align-items: stretch; }',
      '  :root.menu-open .nav { display: flex; }',
      '  .nav .site-menu { flex-direction: column; align-items: stretch; }',
      '  .nav .site-menu-panel { position: static; display: block; box-shadow: none; border: 0; padding-left: 16px; }',
      '}',
    ].join('\n'),
  },
  footer: {
    html: [
      '<div class="bar">',
      '  <div class="wrap">',
      `    <span>© ${t('year')} ${t('site_name')}</span>`,
      `    ${t('menu_footer')}`,
      '  </div>',
      '</div>',
    ].join('\n'),
    css: [
      '.bar { background: #201e1d; color: #9b9797; font-size: 13px; }',
      '.wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 24px; max-width: var(--site-width); margin: 0 auto; padding: 22px 16px; }',
      'a { color: #d7d3d3; text-decoration: none; }',
      '',
      '@media (max-width: 600px) { .wrap { flex-direction: column; text-align: center; } }',
    ].join('\n'),
  },
}

const clone = (value) => JSON.parse(JSON.stringify(value))

export default function headerFooter() {
  return extend(sectionLibrary(), {
    kind: 'header',
    tokens: [],
    can: { create: false, update: false, delete: false },
    pane: 'content',

    devices: DEVICES,
    device: 'desktop',
    stageWidth: 0,
    previewing: false,
    previewError: '',
    previewScroll: 0,
    activating: false,

    formsList: [],
    formsLoaded: false,

    async init() {
      // Captured now: after an await, `$root` of a screen that was closed
      // meanwhile is no longer in the page — see alive().
      this._root = this.$root
      this.schedulePreview = debounce(() => this.renderPreview(), 400)

      this._onMessage = (event) => {
        if (typeof event.data?.spScroll === 'number') this.previewScroll = event.data.spScroll
      }
      window.addEventListener('message', this._onMessage)
      this._resize = new ResizeObserver(() => this.measureStage())

      await Promise.all([this.load(), this.loadForms()])

      // Left for another screen while loading: stop here. Carrying on would
      // move the address back to #/layout and watch a component that is gone.
      if (!this.alive()) return

      const wanted = this.templates.find((p) => String(p.id) === String(this.$store.ui.param))
      const first = wanted ?? this.templates.find((p) => p.kind === this.kind && p.is_active) ?? this.templates.find((p) => p.kind === this.kind)
      if (wanted) this.kind = wanted.kind
      first ? await this.select(first) : await this.startNew()
      if (!this.alive()) return

      this.$watch('draft', () => this.schedulePreview())
      // A field added or retyped on the Fields tab gets its value slot.
      this.$watch('draft.fields', () => { if (this.draft) this.fillValues() })
      this.$nextTick(() => {
        if (this.$refs.stage) this._resize.observe(this.$refs.stage)
        this.measureStage()
      })
    },

    destroy() {
      window.removeEventListener('message', this._onMessage)
      this._resize?.disconnect()
    },

    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get('/site-parts')
        this.templates = payload.data
        this.tokens = payload.tokens
        this.can = payload.can
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    async loadForms() {
      try {
        const payload = await api.get('/forms?per_page=100&status=Active')
        this.formsList = payload.data
      } catch {
        /* a form field then offers nothing to pick */
      } finally {
        this.formsLoaded = true
      }
    },

    // ── the library list ───────────────────────────────────────────────
    get visibleTemplates() {
      const term = this.search.trim().toLowerCase()

      return this.templates
        .filter((p) => p.kind === this.kind)
        .filter((p) => !term || p.name.toLowerCase().includes(term) || (p.description ?? '').toLowerCase().includes(term))
    },

    count(kind) {
      return this.templates.filter((p) => p.kind === kind).length
    },

    async setKind(kind) {
      if (kind === this.kind || !(await this.confirmDiscard())) return

      this.dirty = false
      this.kind = kind
      const first = this.templates.find((p) => p.kind === kind && p.is_active) ?? this.templates.find((p) => p.kind === kind)
      first ? await this.select(first) : await this.startNew()
    },

    usageLabel(part) {
      return part.is_active ? 'In use on the site' : `Saved ${part.updated ?? ''}`
    },

    get noun() {
      return this.kind === 'header' ? 'header' : 'footer'
    },

    /** Still the screen on show — not one closed while a request was out. */
    alive() {
      return !!this._root?.isConnected && this.$store.ui.screen === 'layout'
    },

    async select(part) {
      if (!(await this.confirmDiscard())) return

      this.draft = clone(part)
      this.draft.fields = this.draft.fields ?? []
      this.draft.settings = { sticky: false, caret: 'triangle', ...(this.draft.settings ?? {}) }
      this.fillValues()
      this.collapsed = [...this.fields]
      this.errors = {}
      this.dirty = false
      this.pane = 'content'

      if (part.id && this.alive()) this.$store.ui.go('layout', part.id)
    },

    async startNew() {
      if (!(await this.confirmDiscard())) return

      this.draft = {
        id: null,
        kind: this.kind,
        name: `New ${this.noun}`,
        description: '',
        html: STARTERS[this.kind].html,
        css: STARTERS[this.kind].css,
        fields: [],
        values: {},
        settings: { sticky: this.kind === 'header', caret: 'triangle' },
        is_active: false,
      }
      this.collapsed = []
      this.errors = {}
      this.dirty = true
      this.pane = 'html'
      this.$store.ui.go('layout')
    },

    async duplicate(part) {
      if (!(await this.confirmDiscard())) return

      const copy = clone(part)
      Object.assign(copy, { id: null, name: `${part.name} copy`, is_active: false })

      this.draft = copy
      this.fillValues()
      this.collapsed = [...this.fields]
      this.dirty = true
      this.pane = 'content'
      this.$store.ui.go('layout')
    },

    async confirmDiscard() {
      if (!this.dirty) return true

      return this.$store.dialog.confirm({
        title: 'Discard changes',
        message: `This ${this.noun} has unsaved changes. Leaving it will throw them away.`,
        confirmLabel: 'Discard changes',
        cancelLabel: 'Keep editing',
        danger: true,
      })
    },

    // ── saving, using, deleting ────────────────────────────────────────
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
          values: this.draft.values,
          settings: this.draft.settings,
        }

        const result = this.draft.id
          ? await api.put(`/site-parts/${this.draft.id}`, payload)
          : await api.post('/site-parts', { ...payload, kind: this.draft.kind })

        const folded = this.collapsed.map((f) => f.key)
        this.draft = clone(result.data)
        this.fillValues()
        this.collapsed = this.fields.filter((f) => folded.includes(f.key))
        this.dirty = false

        await this.load()
        this.$store.ui.go('layout', this.draft.id)
        this.$store.ui.notify(this.draft.is_active ? `Saved — the site shows it now` : `${this.noun[0].toUpperCase()}${this.noun.slice(1)} saved`)
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message
      } finally {
        this.saving = false
      }
    },

    async activate() {
      if (!this.draft?.id || this.activating) return

      if (this.dirty) {
        await this.save()
        if (this.dirty) return
      }

      this.activating = true
      try {
        const result = await api.post(`/site-parts/${this.draft.id}/activate`)
        await this.load()
        this.draft.is_active = true
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      } finally {
        this.activating = false
      }
    },

    async remove(part) {
      if (part.is_active) {
        await this.$store.dialog.alert({
          title: `This ${part.kind} is in use`,
          message: `Put another ${part.kind} on the site first, then delete this one.`,
        })
        return
      }

      const ok = await this.$store.dialog.confirm({
        title: `Delete ${part.kind}`,
        message: `Delete “${part.name}”? This cannot be undone.`,
        confirmLabel: `Delete ${part.kind}`,
        danger: true,
      })
      if (!ok) return

      try {
        const result = await api.delete(`/site-parts/${part.id}`)
        await this.load()

        if (this.draft?.id === part.id) {
          this.dirty = false
          const next = this.visibleTemplates[0]
          next ? await this.select(next) : await this.startNew()
        }

        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    // ── the Content tab: this header's own values ──────────────────────
    /** Every field gets a slot, so the inputs always have something to bind. */
    fillValues() {
      if (!this.draft.values || Array.isArray(this.draft.values)) this.draft.values = {}

      for (const field of this.fields) {
        if (!field.key) continue
        const current = this.draft.values[field.key]

        if (field.type === 'group') {
          if (!Array.isArray(current)) this.draft.values[field.key] = []
        } else if (current === undefined || current === null || typeof current === 'object') {
          this.draft.values[field.key] = ''
        }
      }
    },

    /** Fields added on the Fields tab show up on the Content tab at once. */
    get contentFields() {
      return this.fields.filter((f) => f.key)
    },

    setValue(target, key, value) {
      target[key] = value
      this.dirty = true
    },

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

    blankItem(field) {
      return Object.fromEntries((field.fields ?? []).map((sub) => [sub.key, '']))
    },

    addItem(field) {
      this.draft.values[field.key].push(this.blankItem(field))
      this.dirty = true
    },

    duplicateItem(field, index) {
      const list = this.draft.values[field.key]
      list.splice(index + 1, 0, clone(list[index]))
      this.dirty = true
    },

    removeItem(field, index) {
      this.draft.values[field.key].splice(index, 1)
      this.dirty = true
    },

    moveItem(field, index, step) {
      const list = this.draft.values[field.key]
      const to = index + step
      if (to < 0 || to >= list.length) return
      list.splice(to, 0, list.splice(index, 1)[0])
      this.dirty = true
    },

    itemLabel(field, item, index) {
      const first = (field.fields ?? []).find((sub) => sub.type === 'text' && item[sub.key])
      return first ? item[first.key] : `Item ${index + 1}`
    },

    // ── site tokens ────────────────────────────────────────────────────
    get tokenGroups() {
      const groups = {}
      for (const token of this.tokens) (groups[token.group] ??= []).push(token)
      return Object.entries(groups).map(([label, items]) => ({ label, items }))
    },

    /** Site tokens count as known, so the "no field" warning leaves them alone. */
    get pageTokens() {
      return this.tokens.map((token) => token.key)
    },

    /** Puts a token where the cursor is in the HTML editor, and keeps focus there. */
    insert(text) {
      if (!this.draft) return
      if (insertAtCursor(this.$refs.html, text)) return

      // No editor (yet): add it at the end.
      this.draft.html = `${this.draft.html}${text}`
      this.dirty = true
    },

    /** What typing {{ offers: this part's fields, then the site tokens. */
    get codeTokens() {
      const fields = this.fields.filter((f) => f.key).flatMap((field) => [
        { key: field.key, detail: field.type === 'group' ? 'repeat' : (field.label || field.type), group: field.type === 'group' },
        ...(field.type === 'group' ? (field.fields ?? []).filter((s) => s.key).map((s) => ({ key: s.key, detail: `in ${field.key}` })) : []),
      ])
      const site = this.tokens
        .filter((token) => !fields.some((f) => f.key === token.key))
        .map((token) => ({ key: token.key, detail: token.label, info: token.hint }))

      return [...fields, ...site]
    },

    insertToken(key) {
      this.insert(tokenFor(key))
    },

    usesToken(key) {
      return new RegExp(`\\{\\{\\s*${key}\\s*\\}\\}`).test(this.draft?.html ?? '')
    },

    // ── the preview ────────────────────────────────────────────────────
    async renderPreview() {
      if (!this.draft) return
      this.previewing = true
      this.previewError = ''

      try {
        const { html } = await api.post('/site-parts/preview', {
          kind: this.draft.kind,
          name: this.draft.name || 'Draft',
          html: this.draft.html,
          css: this.draft.css,
          fields: this.draft.fields,
          values: this.draft.values,
          settings: this.draft.settings,
        })
        const frame = this.$refs.frame
        if (!frame) return

        // A footer is at the bottom: open the preview there the first time.
        const scroll = this.draft.kind === 'footer' && !this._scrolledFor?.[this.draft.id] ? 99999 : this.previewScroll
        this._scrolledFor = { ...(this._scrolledFor ?? {}), [this.draft.id]: true }
        frame.srcdoc = html.replace('<head>', `<head><script>window.__spScroll=${Math.round(scroll)}<\/script>`)
      } catch (err) {
        this.previewError = err.errors ? Object.values(err.errors)[0][0] : err.message
      } finally {
        this.previewing = false
      }
    },

    measureStage() {
      this.stageWidth = this.$refs.stage?.clientWidth ?? 0
    },

    get frameWidth() {
      return DEVICES[this.device].width
    },

    get frameScale() {
      return this.stageWidth ? Math.min(1, (this.stageWidth - 2) / this.frameWidth) : 1
    },

    get frameStyle() {
      const scale = this.frameScale
      return `width:${this.frameWidth}px;height:${Math.round(420 / scale)}px;transform:scale(${scale});`
    },
  })
}
