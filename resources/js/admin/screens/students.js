import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

/** Learner accounts and how far they have got. Read only; edits live in User management. */
export default function students() {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    query: { search: '', per_page: 10, page: 1 },
    canManage: false,
    loading: true,
    error: '',

    async init() {
      this.searchDebounced = debounce(() => {
        this.query.page = 1
        this.load()
      }, 350)
      await this.load()
    },

    async load() {
      this.loading = true
      this.error = ''
      try {
        const payload = await api.get(`/students?${new URLSearchParams(this.query)}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.canManage = payload.can_manage
      } catch (err) {
        this.error = err.message
        this.rows = []
      } finally {
        this.loading = false
      }
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
      return total ? `Showing ${from}–${to} of ${total}` : 'No students yet'
    },
  }
}
