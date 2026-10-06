import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

const TABS = [
  { key: 'site', label: 'Site' },
  { key: 'email', label: 'Email' },
  { key: 'spam', label: 'Spam protection' },
  { key: 'design', label: 'Design' },
  { key: 'frontend', label: 'Front end' },
  { key: 'lms', label: 'LMS' },
  { key: 'redirects', label: 'Redirects' },
]

/** Tabs whose screens exist; the rest say they are coming. */
const BUILT = ['site', 'email', 'spam', 'design', 'frontend', 'lms', 'redirects']

/** Each tab edits one settings group, saved on its own. */
const GROUP_OF = { site: 'site', email: 'mail', spam: 'spam', design: 'design', frontend: 'frontend', lms: 'lms' }

/** Settings → Design: the values a fresh site starts with (App\Support\SiteSettings). */
const DESIGN_DEFAULTS = {
  primary: '#ec3013', text: '#1c1a19', background: '#f2f1f1',
  body_font: 'Archivo', heading_font: 'Archivo',
  site_width: 1100, content_width: 720, text_size: 16, radius: 0,
}

/** One-click colours; the picker beside them takes any other. */
const DESIGN_SWATCHES = {
  primary: ['#ec3013', '#2563eb', '#0f766e', '#16a34a', '#7c3aed', '#db2777', '#ea580c', '#1c1a19'],
  text: ['#1c1a19', '#111827', '#1e293b', '#3f3f46'],
  background: ['#f2f1f1', '#ffffff', '#f8fafc', '#faf7f2', '#f1f5f9'],
}

/** WCAG relative luminance, for the contrast warning. */
function luminance(hex) {
  const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '')
  if (!m) return null
  const [r, g, b] = m.slice(1).map((h) => {
    const c = parseInt(h, 16) / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrast(a, b) {
  const la = luminance(a)
  const lb = luminance(b)
  if (la === null || lb === null) return null
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)
}

const PROVIDERS = [
  { value: 'log', label: 'Not set up', summary: 'Nothing is sent — mail is written to the log file.', mono: '—' },
  { value: 'gmail', label: 'Gmail', summary: 'A Gmail or Google Workspace account, with an App Password.', mono: 'G' },
  { value: 'zoho', label: 'Zoho Mail', summary: 'A Zoho Mail account, free or with your own domain.', mono: 'Z' },
  { value: 'webmail', label: 'Webmail', summary: 'The mailbox your hosting gives you (cPanel, Plesk, DirectAdmin).', mono: 'W' },
  { value: 'smtp', label: 'Custom SMTP', summary: 'Any other mail server, with every setting in your hands.', mono: 'S' },
]

/** Password and secret fields: sent only when typed, never read back. */
const SECRETS = {
  mail: ['gmail_app_password', 'zoho_password', 'webmail_password', 'smtp_password'],
  spam: ['turnstile_secret', 'recaptcha_secret'],
}

const CAPTCHAS = [
  { value: 'none', label: 'No CAPTCHA', summary: 'The honeypot, time trap and rules below still run.', mono: '—' },
  { value: 'turnstile', label: 'Cloudflare Turnstile', summary: 'Free, usually invisible, no picture puzzles.', mono: 'CF' },
  { value: 'recaptcha', label: 'Google reCAPTCHA v3', summary: 'Invisible; scores each visitor from 0 to 1.', mono: 'G' },
]

export default function settings() {
  return {
    tabs: TABS,
    providers: PROVIDERS,
    captchas: CAPTCHAS,
    spamStats: { month: '', total: 0, reasons: [] },
    keyCheck: null,
    keyChecking: false,
    tab: 'site',

    loading: true,
    error: '',
    can: { update: false, scripts: false },
    options: { zoho_regions: [], timezones: [], date_formats: [], socials: [] },

    site: null,
    mail: null,
    spam: null,
    frontend: null,
    design: null,
    lms: null,
    designSwatches: DESIGN_SWATCHES,
    snapshots: {},
    errors: {},
    saving: false,

    // Settings → Redirects: a list of its own, saved row by row.
    redirects: {
      rows: [], meta: { current_page: 1, last_page: 1, total: 0 },
      search: '', source: 'all', loading: false,
      form: { from: '', to: '', status: 301, note: '' }, errors: {}, saving: false,
    },

    testTo: '',
    testing: false,
    testResult: null,

    async init() {
      const wanted = this.$store.ui.param
      this.tab = TABS.some((t) => t.key === wanted) ? wanted : 'site'
      this.testTo = this.$store.auth.user?.email ?? ''

      // The tab lives in the address, so Back/Forward and links work.
      this.$watch('$store.ui.param', (next) => {
        if (next && next !== this.tab && TABS.some((t) => t.key === next)) this.tab = next
      })

      this.searchRedirects = debounce(() => this.loadRedirects(1), 350)
      if (this.tab === 'redirects') this.loadRedirects()

      try {
        this.adopt(await api.get('/settings'))
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    adopt(payload) {
      this.can = payload.can
      this.options = { ...this.options, ...payload.options }

      for (const group of Object.values(GROUP_OF)) {
        if (!payload[group]) continue
        this[group] = this.blankSecrets(group, payload[group])
        this.snapshots[group] = JSON.stringify(this[group])
      }

      if (payload.spam_stats) this.spamStats = payload.spam_stats
      // The section previews borrow this to look like the site.
      if (payload.design_output) window.__SITE_DESIGN__ = payload.design_output
      this.errors = {}
    },

    /** Secret boxes start empty; `<name>_set` says whether one is saved. */
    blankSecrets(group, values) {
      const copy = { ...values }
      for (const key of SECRETS[group] ?? []) {
        copy[key] = ''
        copy[`${key}_clear`] = false
      }
      return copy
    },

    isBuilt(key) {
      return BUILT.includes(key)
    },

    get group() {
      return GROUP_OF[this.tab]
    },

    isDirty(group) {
      return this[group] !== null && this.snapshots[group] !== undefined && JSON.stringify(this[group]) !== this.snapshots[group]
    },

    get dirty() {
      return this.isDirty(this.group)
    },

    async openTab(key) {
      if (key === this.tab) return

      if (this.dirty) {
        const label = TABS.find((t) => t.key === this.tab)?.label
        const ok = await this.$store.dialog.confirm({
          title: 'Discard changes',
          message: `The ${label} settings have unsaved changes. Leaving this tab will throw them away.`,
          confirmLabel: 'Discard changes',
          cancelLabel: 'Keep editing',
          danger: true,
        })
        if (!ok) return
        this.discard()
      }

      this.tab = key
      this.errors = {}
      this.$store.ui.go('settings', key)
      if (key === 'redirects' && !this.redirects.rows.length) this.loadRedirects()
    },

    discard() {
      const group = this.group
      this[group] = JSON.parse(this.snapshots[group])
      this.errors = {}
      this.testResult = null
    },

    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    /** Only what the server wants: no `_set` flags or read-only extras. */
    payloadFor(group) {
      const payload = { ...this[group] }
      for (const key of Object.keys(payload)) {
        if (key.endsWith('_set') || key === 'app_name') delete payload[key]
      }
      return payload
    },

    async saveGroup(group = this.group) {
      if (this.saving || !this.can.update) return

      this.saving = true
      this.errors = {}
      this.error = ''

      try {
        const result = await api.put(`/settings/${group}`, this.payloadFor(group))
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message
      } finally {
        this.saving = false
      }
    },

    // ── front end: external files ──────────────────────────────────────
    addExternal(type) {
      if (!Array.isArray(this.frontend.external_assets)) this.frontend.external_assets = []
      this.frontend.external_assets.push({ type, url: '', place: type === 'js' ? 'body' : 'head', load: type === 'js' ? 'defer' : 'normal', note: '' })
    },

    moveExternal(index, step) {
      const list = this.frontend.external_assets
      const to = index + step
      if (to < 0 || to >= list.length) return
      list.splice(to, 0, list.splice(index, 1)[0])
    },

    // ── design ─────────────────────────────────────────────────────────
    get limits() {
      return this.options.design_limits ?? { site_width: [760, 1600], content_width: [520, 1000], text_size: [14, 22], radius: [0, 24] }
    },

    get fontGroups() {
      const groups = []
      for (const font of this.options.fonts ?? []) {
        let group = groups.find((g) => g.label === font.group)
        if (!group) groups.push((group = { label: font.group, fonts: [] }))
        group.fonts.push(font)
      }
      return groups
    },

    fontStack(value) {
      return (this.options.fonts ?? []).find((f) => f.value === value)?.stack ?? 'system-ui, sans-serif'
    },

    /** Loads the chosen fonts into the panel, so the samples show them. */
    loadDesignFonts() {
      for (const value of [this.design?.body_font, this.design?.heading_font]) {
        const font = (this.options.fonts ?? []).find((f) => f.value === value)
        if (!font?.google) continue
        const href = `https://fonts.googleapis.com/css2?family=${font.google}&display=swap`
        if (document.querySelector(`link[data-design-font="${font.value}"]`)) continue
        const link = Object.assign(document.createElement('link'), { rel: 'stylesheet', href })
        link.dataset.designFont = font.value
        document.head.appendChild(link)
      }
    },

    resetDesign() {
      Object.assign(this.design, DESIGN_DEFAULTS)
      this.loadDesignFonts()
    },

    /** The sample card, drawn with the unsaved choices. */
    get designSampleStyle() {
      const d = this.design ?? DESIGN_DEFAULTS
      return [
        `--ds-primary:${d.primary}`,
        `--ds-text:${d.text}`,
        `--ds-bg:${d.background}`,
        `--ds-radius:${d.radius}px`,
        `--ds-size:${d.text_size}px`,
        `--ds-body:${this.fontStack(d.body_font)}`,
        `--ds-heading:${this.fontStack(d.heading_font)}`,
      ].join(';')
    },

    /** Says so when the text or a white button label would be hard to read. */
    get contrastWarning() {
      const d = this.design
      if (!d) return ''
      const text = contrast(d.text, d.background)
      const button = contrast('#ffffff', d.primary)
      if (text !== null && text < 4.5) return `The text colour is hard to read on this background (contrast ${text.toFixed(1)}:1 — aim for 4.5 or more).`
      if (button !== null && button < 3) return `White button text is hard to read on this brand colour (contrast ${button.toFixed(1)}:1). A darker brand colour works better.`
      return ''
    },

    // ── site ───────────────────────────────────────────────────────────
    async pickImage(field, title) {
      const media = await this.$store.picker.pick({ kind: 'image', title })
      if (!media) return

      // Kept as a path (/storage/…), so it works on whatever host the site
      // is reached through — not only the one in APP_URL.
      try {
        const url = new URL(media.url, window.location.origin)
        this.site[field] = url.pathname.startsWith('/storage/') ? url.pathname : media.url
      } catch {
        this.site[field] = media.url
      }
    },

    get titlePreview() {
      const name = this.site?.name?.trim() || this.site?.app_name || ''
      return (this.site?.title_format || '{title}').replaceAll('{title}', 'About us').replaceAll('{site}', name)
    },

    // ── redirects ──────────────────────────────────────────────────────
    async loadRedirects(page = 1) {
      const r = this.redirects
      r.loading = true
      try {
        const params = new URLSearchParams({ search: r.search, source: r.source, page })
        const result = await api.get(`/redirects?${params}`)
        r.rows = result.data
        r.meta = result.meta
      } catch (err) {
        this.error = err.message
      } finally {
        r.loading = false
      }
    },

    setRedirectSource(source) {
      this.redirects.source = source
      this.loadRedirects(1)
    },

    async addRedirect() {
      const r = this.redirects
      if (r.saving) return
      r.saving = true
      r.errors = {}

      try {
        const result = await api.post('/redirects', r.form)
        r.form = { from: '', to: '', status: 301, note: '' }
        this.$store.ui.notify(result.message)
        await this.loadRedirects(1)
      } catch (err) {
        r.errors = err.errors || { from: [err.message] }
      } finally {
        r.saving = false
      }
    },

    async removeRedirect(row) {
      const ok = await this.$store.dialog.confirm({
        title: 'Remove redirect',
        message: `${row.from} will stop leading to ${row.to}. Anyone following an old link to it gets "page not found".`,
        confirmLabel: 'Remove',
        danger: true,
      })
      if (!ok) return

      try {
        const result = await api.delete(`/redirects/${row.id}`)
        this.$store.ui.notify(result.message)
        await this.loadRedirects(this.redirects.meta.current_page)
      } catch (err) {
        this.error = err.message
      }
    },

    // ── spam ───────────────────────────────────────────────────────────
    chooseCaptcha(value) {
      if (!this.can.update) return
      this.spam.captcha = value
      this.keyCheck = null
      this.errors = {}
    },

    /** Ask Cloudflare or Google whether the secret on screen (or saved) is real. */
    async checkKey() {
      const provider = this.spam.captcha
      if (this.keyChecking || provider === 'none') return

      this.keyChecking = true
      this.keyCheck = null

      try {
        const result = await api.post('/settings/spam/check', { provider, secret: this.spam[`${provider}_secret`] })
        this.keyCheck = result
      } catch (err) {
        this.keyCheck = { ok: false, message: err.message }
      } finally {
        this.keyChecking = false
      }
    },

    /** Lines in a textarea that the server will use — for the counts under it. */
    lineCount(text) {
      return (text || '').split(/\r?\n/).map((l) => l.trim()).filter(Boolean).length
    },

    // ── email ──────────────────────────────────────────────────────────
    get provider() {
      return this.providers.find((p) => p.value === this.mail?.provider) ?? this.providers[0]
    },

    chooseProvider(value) {
      if (!this.can.update) return
      this.mail.provider = value
      this.errors = {}
      this.testResult = null
    },

    /** The server Zoho's two dropdowns point at, shown so nobody has to guess. */
    get zohoServer() {
      const m = this.mail
      if (!m) return ''
      const prefix = m.zoho_account === 'organization' ? 'smtppro' : 'smtp'
      const domain = m.zoho_region === 'ca' ? 'zohocloud.ca' : `zoho.${m.zoho_region}`
      return `${prefix}.${domain}:${m.zoho_security === 'tls' ? 587 : 465}`
    },

    /** What From will say when the box is left blank. */
    get accountAddress() {
      const m = this.mail
      if (!m) return ''
      return (
        {
          gmail: m.gmail_address,
          zoho: m.zoho_address,
          webmail: m.webmail_username,
          smtp: m.smtp_username,
        }[m.provider] || ''
      )
    },

    passwordPlaceholder(key) {
      if (this.mail?.[`${key}_clear`]) return 'Will be removed when you save'
      return this.mail?.[`${key}_set`] ? '•••••••• saved — leave blank to keep it' : ''
    },

    saveMail() {
      return this.saveGroup('mail')
    },

    async sendTest() {
      if (this.testing) return

      this.testing = true
      this.testResult = null
      this.errors = {}

      try {
        const result = await api.post('/settings/mail/test', { ...this.payloadFor('mail'), test_to: this.testTo })
        this.testResult = { ok: true, message: result.message }
      } catch (err) {
        this.errors = err.errors || {}
        // Field errors mean the test never ran; a bare message is the
        // mail server's answer, already translated into what to do.
        this.testResult = {
          ok: false,
          message: Object.keys(this.errors).length ? 'Fix the highlighted fields first.' : err.message,
        }
      } finally {
        this.testing = false
      }
    },

  }
}
