import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

const TABS = [
  { key: 'signin', label: 'Sign-in & passwords' },
  { key: 'twofactor', label: 'Two-factor' },
  { key: 'sessions', label: 'Sessions' },
  { key: 'ip', label: 'IP rules' },
  { key: 'https', label: 'HTTPS & headers' },
  { key: 'log', label: 'Security log' },
]

/** Tabs whose switches are saved together with the Save button. */
const SETTINGS_TABS = ['signin', 'twofactor', 'ip', 'https']

export default function security() {
  return {
    tabs: TABS,
    tab: 'signin',

    loading: true,
    error: '',
    data: null,
    form: null,
    snapshot: '',
    errors: {},
    saving: false,

    newRule: { value: '', type: 'allow', note: '' },
    ruleErrors: {},

    log: { rows: [], meta: { current_page: 1, last_page: 1, total: 0 }, type: 'all', search: '', loading: false },

    async init() {
      const wanted = this.$store.ui.param
      this.tab = TABS.some((t) => t.key === wanted) ? wanted : 'signin'
      this.searchLog = debounce(() => this.loadLog(1), 350)

      this.$watch('$store.ui.param', (next) => {
        if (next && next !== this.tab && TABS.some((t) => t.key === next)) this.tab = next
      })

      try {
        this.adopt(await api.get('/security'))
        if (this.tab === 'log') this.loadLog()
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    adopt(payload) {
      this.data = payload
      this.form = { ...payload.settings, two_factor_roles: [...(payload.settings.two_factor_roles ?? [])].map(Number) }
      this.snapshot = JSON.stringify(this.form)
      this.errors = {}
    },

    get can() {
      return this.data?.can?.update ?? false
    },

    get dirty() {
      return this.form !== null && JSON.stringify(this.form) !== this.snapshot
    },

    get savesHere() {
      return SETTINGS_TABS.includes(this.tab)
    },

    async openTab(key) {
      if (key === this.tab) return

      // Switches on the settings tabs save together; leaving for a tab that
      // does not save them asks first.
      if (this.dirty && !SETTINGS_TABS.includes(key)) {
        const ok = await this.$store.dialog.confirm({
          title: 'Discard changes',
          message: 'The security settings have unsaved changes. Leaving will throw them away.',
          confirmLabel: 'Discard changes',
          cancelLabel: 'Keep editing',
          danger: true,
        })
        if (!ok) return
        this.form = JSON.parse(this.snapshot)
      }

      this.tab = key
      this.$store.ui.go('security', key)
      if (key === 'log' && !this.log.rows.length) this.loadLog()
    },

    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    async save() {
      if (this.saving || !this.can) return
      this.saving = true
      this.errors = {}
      this.error = ''

      try {
        const result = await api.put('/security', this.form)
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some settings need attention.' : err.message

        // Show the tab the problem is on.
        const fields = Object.keys(this.errors)
        if (fields.includes('ip_rules_enabled')) this.tab = 'ip'
        else if (fields.some((f) => ['hsts', 'force_https', 'headers'].includes(f))) this.tab = 'https'
        else if (fields.some((f) => f.startsWith('two_factor'))) this.tab = 'twofactor'
      } finally {
        this.saving = false
      }
    },

    discard() {
      this.form = JSON.parse(this.snapshot)
      this.errors = {}
    },

    // ── two-factor ─────────────────────────────────────────────────────
    requires(roleId) {
      return this.form.two_factor_roles.includes(roleId)
    },

    toggleRole(roleId) {
      if (!this.can) return
      this.form.two_factor_roles = this.requires(roleId)
        ? this.form.two_factor_roles.filter((id) => id !== roleId)
        : [...this.form.two_factor_roles, roleId]
    },

    get peopleStillToSetUp() {
      return this.data.people.filter((p) => this.form.two_factor_roles.includes(p.role_id) && !p.two_factor).length
    },

    async resetTwoFactor(person) {
      const ok = await this.$store.dialog.confirm({
        title: 'Reset two-factor',
        message: `${person.name} will be signed out and can sign in with just their password, then set two-factor up again. Use this when they have lost their phone and their recovery codes.`,
        confirmLabel: 'Reset two-factor',
        danger: true,
      })
      if (!ok) return
      await this.act(() => api.post(`/security/users/${person.id}/reset-two-factor`))
    },

    // ── sessions ───────────────────────────────────────────────────────
    async endSession(session) {
      const ok = await this.$store.dialog.confirm({
        title: 'Sign out this session',
        message: `${session.user} on ${session.device} (${session.ip ?? 'unknown address'}) will be signed out.`,
        confirmLabel: 'Sign out',
      })
      if (ok) await this.act(() => api.delete(`/security/sessions/${session.key}`))
    },

    async signOutPerson(person) {
      const ok = await this.$store.dialog.confirm({
        title: 'Sign out everywhere',
        message: `Every session ${person.name} has open ends now, including "keep me signed in" browsers.`,
        confirmLabel: 'Sign out everywhere',
        danger: true,
      })
      if (ok) await this.act(() => api.post(`/security/users/${person.id}/sign-out`))
    },

    // ── IP rules ───────────────────────────────────────────────────────
    useMyIp() {
      this.newRule.value = this.data.you.ip
      this.newRule.type = 'allow'
      this.newRule.note = this.newRule.note || 'My address'
    },

    async addRule() {
      this.ruleErrors = {}
      try {
        const result = await api.post('/security/ip-rules', this.newRule)
        this.newRule = { value: '', type: 'allow', note: '' }
        this.adoptKeepingForm(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.ruleErrors = err.errors || {}
        if (!Object.keys(this.ruleErrors).length || !this.ruleErrors.value) this.ruleErrors.value = [err.message]
      }
    },

    async removeRule(rule) {
      const ok = await this.$store.dialog.confirm({
        title: 'Remove IP rule',
        message: `Remove the ${rule.type} rule for ${rule.value}?`,
        confirmLabel: 'Remove',
        danger: true,
      })
      if (!ok) return

      try {
        const result = await api.delete(`/security/ip-rules/${rule.id}`)
        this.adoptKeepingForm(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    get allowRules() {
      return this.data.ip_rules.filter((r) => r.type === 'allow')
    },

    /** Would the switch, as set now, keep the person using it out? */
    get youAreAllowed() {
      const rules = this.data.ip_rules
      if (rules.some((r) => r.type === 'block' && r.matches_you)) return false
      const allows = rules.filter((r) => r.type === 'allow')
      return allows.length === 0 || allows.some((r) => r.matches_you)
    },

    // ── log ────────────────────────────────────────────────────────────
    async loadLog(page = 1) {
      this.log.loading = true
      try {
        const params = new URLSearchParams({ type: this.log.type, search: this.log.search, page })
        const result = await api.get(`/security/log?${params}`)
        this.log.rows = result.data
        this.log.meta = result.meta
      } catch (err) {
        this.error = err.message
      } finally {
        this.log.loading = false
      }
    },

    setLogType(type) {
      this.log.type = type
      this.loadLog(1)
    },

    // ───────────────────────────────────────────────────────────────────
    /** An action that returns the whole payload; unsaved switches survive it. */
    async act(call) {
      try {
        const result = await call()
        this.adoptKeepingForm(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    adoptKeepingForm(payload) {
      const form = this.form
      const dirty = this.dirty
      this.adopt(payload)
      if (dirty) this.form = form
    },
  }
}
