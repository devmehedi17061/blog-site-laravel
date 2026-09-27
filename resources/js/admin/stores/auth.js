/**
 * The session lives in an httpOnly cookie and the signed-in user is printed
 * into the page by panel.blade.php, so this store only reads that payload and
 * answers permission questions. Signing in and out are plain Blade forms.
 */
export default {
  user: null,
  permissions: [],

  init() {
    const payload = window.__ADMIN__ ?? {}

    this.user = payload.user ?? null
    this.permissions = payload.user?.permissions ?? []
  },

  /** A list means "any of these". */
  can(permission) {
    if (this.user?.role?.slug === 'super-admin') return true
    if (Array.isArray(permission)) return permission.some((p) => this.permissions.includes(p))

    return this.permissions.includes(permission)
  },

  logout() {
    document.getElementById('logout-form')?.submit()
  },
}
