import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resource-table.js'

/**
 * Readable and still long: no 0/O or 1/l/I to misread off a screen. Always
 * one of each kind — lower, upper, digit, symbol — so it passes whatever
 * password rules Security has switched on.
 */
function generatePassword(length = 16) {
  const sets = ['abcdefghijkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#$%*?']
  const all = sets.join('')
  const random = (max) => crypto.getRandomValues(new Uint32Array(1))[0] % max

  const chars = sets.map((set) => set[random(set.length)])
  while (chars.length < length) chars.push(all[random(all.length)])

  // Shuffle, so the guaranteed characters are not always first.
  for (let i = chars.length - 1; i > 0; i--) {
    const j = random(i + 1)
    ;[chars[i], chars[j]] = [chars[j], chars[i]]
  }

  return chars.join('')
}

export default function users() {
  const table = resourceTable({
    endpoint: 'users',
    label: 'User',
    blank: () => ({ id: null, name: '', email: '', role_id: '', status: 'Active', password: '' }),
  })

  table.query.role = 'All'
  table.options = { statuses: [], roles: [], users: [] }

  const baseSave = table.save
  const baseOpenCreate = table.openCreate
  const baseOpenEdit = table.openEdit

  // extend(), not spread — spread would flatten the table's getters.
  return extend(table, {
    columns: [
      { key: 'name', label: 'User', sortable: true },
      { key: 'role', label: 'Role', sortable: false },
      { key: 'status', label: 'Status', sortable: true },
      { key: 'content', label: 'Content', sortable: false },
      { key: 'last_seen', label: 'Last seen', sortable: true, sortKey: 'last_seen_at' },
      { key: 'joined', label: 'Joined', sortable: true, sortKey: 'created_at' },
    ],

    showPassword: false,

    get statusFilters() {
      return ['All', ...(this.options.statuses ?? [])]
    },

    setRole(value) {
      this.query.role = value
      this.applyFilters()
    },

    statusTag(status) {
      return status === 'Active' ? 'tag-success' : 'tag-accent-2'
    },

    roleTag(role) {
      if (!role) return 'tag-outline'
      return role.slug === 'super-admin' ? 'tag-accent' : 'tag-neutral'
    },

    contentSummary(row) {
      const parts = [
        [row.posts_count, 'post'],
        [row.pages_count, 'page'],
        [row.media_count, 'file'],
      ].filter(([count]) => count > 0)

      if (!parts.length) return '—'
      return parts.map(([count, noun]) => `${count} ${noun}${count === 1 ? '' : 's'}`).join(' · ')
    },

    // ── create / edit ──────────────────────────────────────────────────
    openCreate() {
      baseOpenCreate.call(this)
      this.showPassword = false
      // The least powerful role this person may hand out is the safe default.
      const assignable = this.options.roles.filter((r) => r.assignable)
      this.modal.draft.role_id = assignable.at(-1)?.id ?? ''
    },

    openEdit(row) {
      baseOpenEdit.call(this, row)
      this.showPassword = false
      this.modal.draft.password = ''
      this.modal.draft.role_id = row.role_id ?? ''
    },

    /**
     * A role the editor may not assign stays listed but disabled — unless
     * it is the one the account already has, which must stay selectable or
     * the form could not be saved unchanged.
     */
    roleDisabled(role) {
      if (this.modal?.draft?.is_self) return role.id !== this.modal.draft.role_id
      return !role.assignable && role.id !== this.modal?.draft?.role_id
    },

    get selectedRole() {
      return this.options.roles.find((r) => r.id === Number(this.modal?.draft?.role_id)) ?? null
    },

    generate() {
      this.modal.draft.password = generatePassword()
      this.showPassword = true
    },

    async save() {
      const creating = this.modal?.mode === 'new'
      await baseSave.call(this)

      // Still open means the server refused it.
      if (this.modal) return

      if (creating) this.bumpCount(1)
      this.loadOptions()
    },

    // ── delete ─────────────────────────────────────────────────────────
    askDelete(row) {
      this.confirming = row
      this.reassignTo = String(this.$store.auth.user?.id ?? '')
    },

    reassignTo: '',

    get heirs() {
      return this.options.users.filter((u) => u.id !== this.confirming?.id)
    },

    get ownsContent() {
      const row = this.confirming
      return !!row && [row.posts_count, row.pages_count, row.media_count].some((n) => n > 0)
    },

    async confirmDelete() {
      if (!this.confirming) return

      const row = this.confirming
      const body = { reassign_to: this.ownsContent && this.reassignTo ? Number(this.reassignTo) : null }
      this.confirming = null

      try {
        const result = await api.delete(`/users/${row.id}`, body)
        this.$store.ui.notify(result?.message || 'User deleted')
        this.bumpCount(-1)
        await Promise.all([this.load(), this.loadOptions()])
      } catch (err) {
        this.error = err.message
      }
    },

    bumpCount(delta) {
      const counts = this.$store.ui.counts
      if (typeof counts.users === 'number') {
        this.$store.ui.counts = { ...counts, users: Math.max(0, counts.users + delta) }
      }
    },
  })
}
