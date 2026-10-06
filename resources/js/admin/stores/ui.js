import { api } from '../api.js'

/**
 * Sidebar structure, hash routing and the toast queue. Every entry carries
 * the permission that gates it, so the nav hides what the role cannot open.
 */
const NAV_GROUPS = [
  {
    label: 'Overview',
    items: [{ key: 'dashboard', label: 'Dashboard', mono: 'DB', permission: null }],
  },
  {
    label: 'Content',
    items: [
      { key: 'pages', label: 'Pages', mono: 'PG', permission: 'pages.view' },
      { key: 'posts', label: 'Posts', mono: 'PO', permission: 'posts.view' },
      { key: 'categories', label: 'Categories', mono: 'CT', permission: 'categories.view' },
      { key: 'media', label: 'Media library', mono: 'MD', permission: 'media.view' },
      { key: 'menu', label: 'Menu control', mono: 'MN', permission: 'menu.view' },
      { key: 'trash', label: 'Trash', mono: 'TR', permission: ['posts.delete', 'pages.delete', 'media.delete', 'courses.delete'] },
    ],
  },
  {
    label: 'LMS',
    items: [
      { key: 'courses', label: 'Courses', mono: 'CO', permission: 'courses.view' },
      { key: 'payments', label: 'Payments', mono: 'PY', permission: 'enrollments.view' },
      { key: 'enrollments', label: 'Enrollments', mono: 'EN', permission: 'enrollments.view' },
      { key: 'students', label: 'Students', mono: 'SD', permission: 'enrollments.view' },
    ],
  },
  {
    label: 'Builders',
    items: [
      { key: 'layout', label: 'Header & footer', mono: 'HF', permission: 'layout.view' },
      { key: 'sections', label: 'Section library', mono: 'SL', permission: 'sections.view' },
      { key: 'forms', label: 'Form builder', mono: 'FB', permission: 'forms.view' },
      { key: 'inbox', label: 'Form inbox', mono: 'IN', permission: 'forms.view' },
    ],
  },
  {
    label: 'Access',
    items: [
      { key: 'users', label: 'User management', mono: 'US', permission: 'users.view' },
      { key: 'roles', label: 'Roles & permissions', mono: 'RP', permission: 'roles.view' },
      { key: 'security', label: 'Security', mono: 'SC', permission: 'security.view' },
    ],
  },
  {
    label: 'System',
    items: [{ key: 'settings', label: 'Settings', mono: 'ST', permission: 'settings.view' }],
  },
]

const TITLES = {
  ...Object.fromEntries(NAV_GROUPS.flatMap((group) => group.items.map((item) => [item.key, item.label]))),
  // Reached from the header, not the sidebar: everyone has one.
  account: 'My account',
}

// Screens not in this list land in later parts; the shell shows a
// placeholder for them rather than a dead link.
const BUILT = ['dashboard', 'layout', 'trash', 'posts', 'courses', 'payments', 'enrollments', 'students', 'categories', 'media', 'pages', 'sections', 'forms', 'inbox', 'menu', 'users', 'roles', 'settings', 'security', 'account']

export default {
  screen: 'dashboard',
  param: null,
  collapsed: false,
  query: '',
  toast: null,
  counts: {},
  groups: NAV_GROUPS,

  /**
   * What this account owes before the rest of the panel opens:
   * 'two_factor_required', 'password_expired' or null. While set, every
   * route leads to My account (the API refuses the rest anyway).
   */
  owes: null,

  get sidebarWidth() {
    return this.collapsed ? 74 : 236
  },

  get showLabels() {
    return !this.collapsed
  },

  get crumb() {
    return TITLES[this.screen] || 'Dashboard'
  },

  /** The sidebar section a screen belongs to, used as its page kicker. */
  groupOf(key) {
    return NAV_GROUPS.find((group) => group.items.some((item) => item.key === key))?.label ?? ''
  },

  get isBuilt() {
    return BUILT.includes(this.screen)
  },

  init() {
    this.owes = window.__ADMIN__?.owes ?? null

    // On a phone or small tablet the full sidebar would leave the screen
    // a sliver: start with it folded to its icons.
    if (window.matchMedia('(max-width: 900px)').matches) this.collapsed = true
    this.readHash()

    // Signed in with a recovery code: say how many are left, wherever the
    // panel opens — running out means being locked out for good.
    const left = window.__ADMIN__?.recovery_notice
    if (left !== undefined && left !== null) {
      this.notify(left > 0
        ? `You signed in with a recovery code — ${left} left. Make new ones under My account if that is low.`
        : 'You used your last recovery code. Make new ones under My account now.', 'warn')
    }
    window.addEventListener('hashchange', () => this.readHash())
  },

  /** `#/posts` is the list, `#/posts/new` and `#/posts/12` the editor. */
  readHash() {
    const parts = window.location.hash.replace(/^#\/?/, '').split('/').filter(Boolean)
    const key = parts[0] || 'dashboard'

    if (this.owes && key !== 'account') {
      this.go('account')
      return
    }

    this.screen = TITLES[key] ? key : 'dashboard'
    this.param = parts[1] ?? null
  },

  /** Called when the API says the account owes something (see api.js). */
  owe(code) {
    this.owes = code
    if (this.screen !== 'account') this.go('account')
  },

  /** The account has done what it owed: the panel opens up again. */
  settle() {
    this.owes = null
  },

  go(key, param = null) {
    if (this.owes && key !== 'account') {
      key = 'account'
      param = null
    }

    window.location.hash = param === null ? `#/${key}` : `#/${key}/${param}`
    this.screen = key
    this.param = param === null ? null : String(param)
  },

  /** True while a record is open in a full-page editor rather than a list. */
  get editing() {
    return this.param !== null
  },

  isActive(key) {
    return this.screen === key
  },

  countFor(key) {
    return this.counts[key]
  },

  clearingCache: false,

  async clearCache() {
    if (this.clearingCache) return
    this.clearingCache = true

    try {
      const result = await api.post('/system/clear-cache')
      this.notify(result.message)
    } catch (err) {
      this.notify(err.message, 'warn')
    } finally {
      this.clearingCache = false
    }
  },

  notify(message, tone = 'accent') {
    this.toast = { message, tone }
    clearTimeout(this._toastTimer)
    // A warning stays long enough to be read.
    this._toastTimer = setTimeout(() => (this.toast = null), tone === 'warn' ? 8000 : 3200)
  },

  toggleSidebar() {
    this.collapsed = !this.collapsed
  },
}
