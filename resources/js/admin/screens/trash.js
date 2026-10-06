import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

/** The Trash tabs, each shown only to roles that can see that content. */
const TABS = [
  { key: 'posts', label: 'Posts', permission: 'posts.view', noun: 'post' },
  { key: 'pages', label: 'Pages', permission: 'pages.view', noun: 'page' },
  { key: 'media', label: 'Media', permission: 'media.view', noun: 'file' },
  { key: 'courses', label: 'Courses', permission: 'courses.view', noun: 'course' },
]

/**
 * Deleted posts, pages and files: restore them, delete them for good, or
 * leave them to be emptied automatically after the server's retention days.
 */
export default function trash() {
  return {
    type: 'posts',
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    counts: {},
    days: 30,
    search: '',
    page: 1,
    selected: [],
    loading: true,
    busy: false,
    error: '',

    get tabs() {
      return TABS.filter((tab) => this.$store.auth.can(tab.permission))
    },

    get tab() {
      return TABS.find((tab) => tab.key === this.type)
    },

    init() {
      this.searchDebounced = debounce(() => { this.page = 1; this.load() }, 350)
      this.type = this.tabs[0]?.key ?? 'posts'
      this.load()
    },

    async load() {
      this.loading = true
      this.error = ''

      try {
        const query = new URLSearchParams({ type: this.type, search: this.search, page: this.page })
        const payload = await api.get(`/trash?${query}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.days = payload.days
        this.setCounts(payload.counts)
        // Keep only what is still on this page.
        this.selected = this.selected.filter((id) => this.rows.some((row) => row.id === id))

        if (this.meta.current_page > this.meta.last_page && this.meta.last_page >= 1) {
          this.page = this.meta.last_page
          return this.load()
        }
      } catch (err) {
        this.error = err.message
        this.rows = []
      } finally {
        this.loading = false
      }
    },

    /** Also feeds the number beside "Trash" in the sidebar. */
    setCounts(counts) {
      this.counts = counts ?? {}
      const total = Object.values(this.counts).reduce((sum, n) => sum + n, 0)
      this.$store.ui.counts = { ...this.$store.ui.counts, trash: total }
    },

    setType(key) {
      if (this.type === key) return
      this.type = key
      this.page = 1
      this.search = ''
      this.selected = []
      this.load()
    },

    goToPage(page) {
      if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) return
      this.page = page
      this.load()
    },

    // ── selection ──────────────────────────────────────────────────────
    isSelected(id) {
      return this.selected.includes(id)
    },

    toggle(id) {
      this.selected = this.isSelected(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id]
    },

    get allSelected() {
      return this.rows.length > 0 && this.rows.every((row) => this.isSelected(row.id))
    },

    toggleAll() {
      this.selected = this.allSelected ? [] : this.rows.map((row) => row.id)
    },

    get selectedRows() {
      return this.rows.filter((row) => this.isSelected(row.id))
    },

    get canRestoreSelected() {
      return this.selectedRows.length > 0 && this.selectedRows.every((row) => row.can.restore)
    },

    get canDeleteSelected() {
      return this.selectedRows.length > 0 && this.selectedRows.every((row) => row.can.delete)
    },

    get canEmpty() {
      return this.rows.some((row) => row.can.delete)
    },

    // ── actions ────────────────────────────────────────────────────────
    what(rows) {
      return rows.length === 1 ? `“${rows[0].title}”` : `${rows.length} ${this.tab.noun}s`
    },

    async restore(rows) {
      if (this.busy || !rows.length) return
      this.busy = true

      try {
        const payload = await api.post(`/trash/${this.type}/restore`, { ids: rows.map((row) => row.id) })
        this.$store.ui.notify(payload.message)
        this.selected = this.selected.filter((id) => !rows.some((row) => row.id === id))
        await this.load()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      } finally {
        this.busy = false
      }
    },

    async deleteForGood(rows) {
      if (this.busy || !rows.length) return

      const media = this.type === 'media'
      const ok = await this.$store.dialog.confirm({
        title: 'Delete for good',
        message: `Delete ${this.what(rows)} for good? This cannot be undone${media ? ' — the file is removed from disk, and any post still showing it will lose the image' : ''}.`,
        confirmLabel: 'Delete for good',
        danger: true,
      })
      if (!ok) return

      this.busy = true
      try {
        const payload = await api.post(`/trash/${this.type}/delete`, { ids: rows.map((row) => row.id) })
        this.$store.ui.notify(payload.message)
        this.selected = this.selected.filter((id) => !rows.some((row) => row.id === id))
        await this.load()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      } finally {
        this.busy = false
      }
    },

    async emptyTrash() {
      if (this.busy) return

      const ok = await this.$store.dialog.confirm({
        title: `Empty the ${this.tab.label.toLowerCase()} trash`,
        message: `Delete every ${this.tab.noun} in the trash for good (${this.counts[this.type] ?? 0} in total)? This cannot be undone.`,
        confirmLabel: 'Empty trash',
        danger: true,
      })
      if (!ok) return

      this.busy = true
      try {
        const payload = await api.post(`/trash/${this.type}/empty`)
        this.$store.ui.notify(payload.message, payload.message.includes('left for them') ? 'warn' : 'accent')
        this.selected = []
        this.page = 1
        await this.load()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      } finally {
        this.busy = false
      }
    },

    daysLabel(row) {
      if (row.days_left <= 0) return 'Deleted at the next clean-up'
      return row.days_left === 1 ? '1 day left' : `${row.days_left} days left`
    },
  }
}
