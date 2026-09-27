import { api } from '../api.js'

/**
 * Roles on the left, the one being edited on the right. The permission
 * matrix is built from /roles/options, so a module added to the seeder
 * shows up here without touching this file.
 */
export default function roles() {
  return {
    roles: [],
    catalog: { abilities: [], groups: [], scopes: [] },

    loading: true,
    error: '',

    selectedId: null,
    creating: false,
    form: null,
    snapshot: '',
    errors: {},
    message: '',
    saving: false,

    confirming: null,
    moveUsersTo: '',

    async init() {
      this.loading = true
      this.error = ''

      try {
        const [list, catalog] = await Promise.all([api.get('/roles'), api.get('/roles/options')])
        this.roles = list.data
        this.catalog = catalog

        // Open the first role, so the matrix is not an empty panel.
        if (!this.form && this.roles.length) this.select(this.roles[0], true)
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    // ── the list ───────────────────────────────────────────────────────
    get selected() {
      return this.roles.find((r) => r.id === this.selectedId) ?? null
    },

    isSelected(role) {
      return !this.creating && role.id === this.selectedId
    },

    permissionCount(role) {
      return role.is_locked ? 'All permissions' : `${role.permissions.length} of ${this.allSlugs.length}`
    },

    get allSlugs() {
      return this.catalog.groups.flatMap((g) => g.modules.flatMap((m) => Object.values(m.cells).map((c) => c.slug)))
    },

    get dirty() {
      return this.form !== null && JSON.stringify(this.form) !== this.snapshot
    },

    /** Leaving a role with unsaved ticks asks first. */
    async leave() {
      if (!this.dirty) return true

      return this.$store.dialog.confirm({
        title: 'Discard changes',
        message: 'This role has unsaved changes. Leaving it will throw them away.',
        confirmLabel: 'Discard changes',
        cancelLabel: 'Keep editing',
        danger: true,
      })
    },

    async select(role, force = false) {
      if (!force && this.isSelected(role)) return
      if (!force && !(await this.leave())) return

      this.creating = false
      this.selectedId = role.id
      this.load({
        name: role.name,
        description: role.description ?? '',
        scope: role.scope,
        permissions: [...role.permissions],
      })
    },

    async startNew(from = null) {
      if (!(await this.leave())) return

      this.creating = true
      this.selectedId = null
      this.load({
        name: from ? `${from.name} copy` : '',
        description: from?.description ?? '',
        scope: from && from.scope !== 'Global' ? from.scope : 'Content',
        // A copy keeps only what this person may grant.
        permissions: from ? from.permissions.filter((slug) => this.grantable(slug)) : [],
      })
    },

    load(form) {
      this.form = form
      this.snapshot = JSON.stringify(form)
      this.errors = {}
      this.message = ''
    },

    discard() {
      this.form = JSON.parse(this.snapshot)
      this.errors = {}
      this.message = ''
    },

    // ── what may be changed ────────────────────────────────────────────
    /** Locked, your own, or more powerful than you: shown, not edited. */
    get readOnly() {
      if (this.creating) return !this.$store.auth.can('roles.create')
      return !this.selected?.can.update
    },

    get readOnlyReason() {
      const role = this.selected
      if (this.creating || !role) return ''
      if (role.is_locked) return 'This role is locked. It always holds every permission, including ones added later.'
      if (role.is_own) return 'This is your own role. Another admin has to change it, so nobody can widen their own access.'
      if (!this.$store.auth.can('roles.update')) return 'You can look at roles but not change them.'
      return 'This role has access you do not hold yourself, so you cannot change it.'
    },

    grantable(slug) {
      return this.$store.auth.can(slug)
    },

    // ── the matrix ─────────────────────────────────────────────────────
    has(slug) {
      if (!this.creating && this.selected?.is_locked) return true
      return this.form?.permissions.includes(slug) ?? false
    },

    cellDisabled(cell) {
      return !cell || this.readOnly || !cell.grantable
    },

    /**
     * Every screen is gated on its view permission, so create, update or
     * delete without view would be a permission nobody could use. Ticking
     * one ticks view; unticking view clears the rest of the row.
     */
    toggle(module, ability) {
      const cell = module.cells[ability]
      if (this.cellDisabled(cell)) return

      if (this.has(cell.slug)) {
        const drop = ability === 'view' ? Object.values(module.cells).map((c) => c.slug) : [cell.slug]
        this.form.permissions = this.form.permissions.filter((slug) => !drop.includes(slug))
        return
      }

      const add = [cell.slug]
      if (ability !== 'view' && module.cells.view && !this.cellDisabled(module.cells.view)) add.push(module.cells.view.slug)
      this.form.permissions = [...new Set([...this.form.permissions, ...add])]
    },

    /** The cells of a module, a column or a group that this person may change. */
    editable(cells) {
      return cells.filter((cell) => !this.cellDisabled(cell))
    },

    state(cells) {
      const usable = this.editable(cells)
      if (!usable.length) return 'none'
      const on = usable.filter((cell) => this.has(cell.slug)).length
      if (on === 0) return 'none'
      return on === usable.length ? 'all' : 'some'
    },

    setAll(cells, on) {
      const slugs = this.editable(cells).map((c) => c.slug)
      this.form.permissions = on
        ? [...new Set([...this.form.permissions, ...slugs])]
        : this.form.permissions.filter((slug) => !slugs.includes(slug))
    },

    flip(cells) {
      if (this.readOnly) return
      this.setAll(cells, this.state(cells) !== 'all')
    },

    rowCells(module) {
      return Object.values(module.cells)
    },

    columnCells(ability) {
      return this.catalog.groups.flatMap((g) => g.modules.map((m) => m.cells[ability]).filter(Boolean))
    },

    groupCells(group) {
      return group.modules.flatMap((m) => this.rowCells(m))
    },

    get everything() {
      return this.catalog.groups.flatMap((g) => this.groupCells(g))
    },

    get grantedCount() {
      if (!this.creating && this.selected?.is_locked) return this.allSlugs.length
      return this.form?.permissions.length ?? 0
    },

    get scopeHint() {
      return this.catalog.scopes.find((s) => s.name === this.form?.scope)?.description ?? ''
    },

    // ── saving ─────────────────────────────────────────────────────────
    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    async save() {
      if (!this.form || this.saving || this.readOnly) return

      this.saving = true
      this.errors = {}
      this.message = ''

      try {
        const result = this.creating
          ? await api.post('/roles', this.form)
          : await api.put(`/roles/${this.selectedId}`, this.form)

        const saved = result.data
        const index = this.roles.findIndex((r) => r.id === saved.id)

        if (index === -1) {
          this.roles = [...this.roles, saved]
          this.$store.ui.counts = { ...this.$store.ui.counts, roles: (this.$store.ui.counts.roles ?? 0) + 1 }
        } else {
          this.roles.splice(index, 1, saved)
        }

        this.$store.ui.notify(this.creating ? `Role "${saved.name}" created` : `Role "${saved.name}" saved`)
        this.select(saved, true)
      } catch (err) {
        this.errors = err.errors || {}
        this.message = Object.keys(this.errors).length ? '' : err.message
      } finally {
        this.saving = false
      }
    },

    // ── delete ─────────────────────────────────────────────────────────
    askDelete(role) {
      this.confirming = role
      // Somewhere they can land that this person may assign, and not this one.
      const fallback = this.moveTargets.find((r) => r.slug === 'viewer') ?? this.moveTargets.at(-1)
      this.moveUsersTo = fallback ? String(fallback.id) : ''
    },

    get moveTargets() {
      return this.roles.filter((r) => r.id !== this.confirming?.id && (this.$store.auth.user?.role?.slug === 'super-admin' || (!r.is_locked && r.permissions.every((slug) => this.grantable(slug)))))
    },

    cancelDelete() {
      this.confirming = null
    },

    async confirmDelete() {
      if (!this.confirming) return

      const role = this.confirming
      const body = { move_users_to: role.users_count > 0 && this.moveUsersTo ? Number(this.moveUsersTo) : null }
      this.confirming = null

      try {
        const result = await api.delete(`/roles/${role.id}`, body)
        this.roles = result.data
        this.$store.ui.counts = { ...this.$store.ui.counts, roles: this.roles.length }
        this.$store.ui.notify(result.message)

        if (this.selectedId === role.id) {
          this.form = null
          this.snapshot = ''
          if (this.roles.length) this.select(this.roles[0], true)
        }
      } catch (err) {
        this.error = err.message
      }
    },
  }
}
