import { api } from '../api.js'
import { debounce } from './debounce.js'

/**
 * The shared behaviour behind every list screen: paged fetching, search,
 * status filter, sorting, and a create/edit modal with server-side
 * validation errors. A screen supplies the endpoint and its blank record.
 *
 *   resourceTable({ endpoint: 'posts', blank: () => ({ title: '', status: 'Draft' }) })
 */
export function resourceTable({ endpoint, blank, label = 'record', trashed = false }) {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    options: { statuses: [], categories: [], authors: [] },

    loading: true,
    error: '',

    query: {
      search: '',
      status: 'All',
      per_page: 10,
      page: 1,
      sort: 'created_at',
      direction: 'desc',
    },

    modal: null,
    confirming: null,

    // ── fetching ───────────────────────────────────────────────────────
    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get(`/${endpoint}?${new URLSearchParams(this.query)}`)
        this.rows = payload.data
        this.meta = payload.meta

        // Deleting the last row of the last page leaves us past the end.
        if (this.meta.current_page > this.meta.last_page && this.meta.last_page >= 1) {
          this.query.page = this.meta.last_page
          return this.load()
        }
      } catch (err) {
        this.error = err.message
        this.rows = []
      } finally {
        this.loading = false
      }
    },

    async loadOptions() {
      try {
        this.options = await api.get(`/${endpoint}/options`)
      } catch {
        /* the form falls back to free text if the lists cannot be read */
      }
    },

    async init() {
      this.searchDebounced = debounce(() => this.applyFilters(), 350)
      await Promise.all([this.load(), this.loadOptions()])
    },

    // ── filtering, sorting, paging ─────────────────────────────────────
    applyFilters() {
      this.query.page = 1
      this.load()
    },

    setStatus(status) {
      this.query.status = status
      this.applyFilters()
    },

    setPerPage(value) {
      this.query.per_page = Number(value)
      this.applyFilters()
    },

    sortBy(column) {
      if (this.query.sort === column) {
        this.query.direction = this.query.direction === 'asc' ? 'desc' : 'asc'
      } else {
        this.query.sort = column
        this.query.direction = 'asc'
      }
      this.query.page = 1
      this.load()
    },

    sortIcon(column) {
      if (this.query.sort !== column) return ''
      return this.query.direction === 'asc' ? '▲' : '▼'
    },

    goToPage(page) {
      if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) return
      this.query.page = page
      this.load()
    },

    get pageNumbers() {
      const total = this.meta.last_page || 1
      const current = this.meta.current_page || 1
      const start = Math.max(1, Math.min(current - 2, total - 4))
      const end = Math.min(total, start + 4)

      return Array.from({ length: end - start + 1 }, (_, i) => start + i)
    },

    get resultCount() {
      const { from, to, total } = this.meta
      if (!total) return 'No records'
      return `Showing ${from}–${to} of ${total}`
    },

    get isEmpty() {
      return !this.loading && this.rows.length === 0
    },

    // ── create / edit ──────────────────────────────────────────────────
    openCreate() {
      this.modal = { mode: 'new', draft: blank(), errors: {}, message: '', saving: false }
    },

    openEdit(row) {
      this.modal = { mode: 'edit', draft: { ...blank(), ...row }, errors: {}, message: '', saving: false }
    },

    closeModal() {
      this.modal = null
    },

    fieldError(field) {
      return this.modal?.errors?.[field]?.[0] || ''
    },

    async save() {
      if (!this.modal || this.modal.saving) return

      this.modal.saving = true
      this.modal.errors = {}
      this.modal.message = ''

      const { mode, draft } = this.modal

      try {
        if (mode === 'new') {
          await api.post(`/${endpoint}`, draft)
        } else {
          await api.put(`/${endpoint}/${draft.id}`, draft)
        }

        this.modal = null
        this.$store.ui.notify(mode === 'new' ? `${label} created` : `${label} saved`)
        await this.load()
      } catch (err) {
        this.modal.errors = err.errors || {}
        this.modal.message = Object.keys(err.errors || {}).length ? '' : err.message
      } finally {
        if (this.modal) this.modal.saving = false
      }
    },

    // ── delete ─────────────────────────────────────────────────────────
    askDelete(row) {
      this.confirming = row ?? null
    },

    cancelDelete() {
      this.confirming = null
    },

    async confirmDelete() {
      if (!this.confirming) return

      const row = this.confirming
      this.confirming = null

      try {
        await api.delete(`/${endpoint}/${row.id}`)
        this.$store.ui.notify(trashed ? `${label} moved to the trash` : `${label} deleted`)
        await this.load()
      } catch (err) {
        this.error = err.message
      }
    },
  }
}
