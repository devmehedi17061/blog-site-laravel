import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

/** LMS → Q&A: lesson questions across courses, unanswered first. */
export default function discussions() {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, total: 0 },
    summary: { Open: 0, Answered: 0 },
    courses: [],
    query: { status: 'Open', course_id: '', search: '', page: 1 },
    replies: {},
    busy: null,
    loading: true,
    error: '',

    async init() {
      this.searchDebounced = debounce(() => this.applyFilters(), 350)
      await this.load()
    },

    async load() {
      this.loading = true
      this.error = ''
      try {
        const query = { ...this.query }
        if (!query.course_id) delete query.course_id
        const payload = await api.get(`/discussions?${new URLSearchParams(query)}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.summary = payload.summary
        this.courses = payload.courses
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    applyFilters() {
      this.query.page = 1
      this.load()
    },

    setStatus(status) {
      this.query.status = status
      this.applyFilters()
    },

    goToPage(page) {
      if (page < 1 || page > this.meta.last_page) return
      this.query.page = page
      this.load()
    },

    refreshBadge() {
      this.$store.ui.counts = { ...this.$store.ui.counts, discussions: this.summary.Open || undefined }
    },

    async reply(row) {
      const body = (this.replies[row.id] || '').trim()
      if (!body || this.busy) return
      this.busy = row.id
      try {
        const result = await api.post(`/discussions/${row.id}/reply`, { body })
        this.replies[row.id] = ''
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.errors?.body?.[0] ?? err.message, 'warn')
      } finally {
        this.busy = null
      }
    },

    async toggle(row) {
      try {
        const result = await api.post(`/discussions/${row.id}/resolve`)
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      }
    },

    async remove(row) {
      const ok = await this.$store.dialog.confirm({
        title: 'Delete question',
        message: 'The question and its replies are deleted for everyone.',
        confirmLabel: 'Delete',
        danger: true,
      })
      if (!ok) return
      try {
        await api.delete(`/discussions/${row.id}`)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      }
    },
  }
}
