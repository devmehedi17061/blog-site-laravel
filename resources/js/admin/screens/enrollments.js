import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

function blankEnroll() {
  return { course_id: '', email: '', name: '', amount: '', payment_method: '', transaction_id: '', note: '' }
}

/**
 * Who is on which course. Paid sign-ups wait here as Pending with the
 * payment the student reported; Approve opens the course for them.
 */
export default function enrollments() {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    summary: { Active: 0, Pending: 0, Cancelled: 0 },
    options: { statuses: [], courses: [], payment_methods: [], currency: '' },
    query: { search: '', status: 'All', course_id: '', per_page: 10, page: 1 },
    loading: true,
    error: '',
    busy: null,

    // "Enroll a student" and "Edit" share one modal.
    modal: null,

    async init() {
      // Arriving from the dashboard's "waiting for approval" link.
      if (this.$store.ui.param === 'pending') this.query.status = 'Pending'
      this.searchDebounced = debounce(() => this.applyFilters(), 350)
      await Promise.all([this.load(), this.loadOptions()])
    },

    async load() {
      this.loading = true
      this.error = ''
      try {
        const query = { ...this.query }
        if (query.status === 'All') delete query.status
        if (!query.course_id) delete query.course_id
        const payload = await api.get(`/enrollments?${new URLSearchParams(query)}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.summary = payload.summary
      } catch (err) {
        this.error = err.message
        this.rows = []
      } finally {
        this.loading = false
      }
    },

    async loadOptions() {
      try {
        this.options = await api.get('/enrollments/options')
      } catch {
        /* the filters fall back to "All" */
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
      return total ? `Showing ${from}–${to} of ${total}` : 'No records'
    },

    get statusFilters() {
      return ['All', 'Pending', 'Active', 'Cancelled']
    },

    countFor(status) {
      if (status === 'All') return Object.values(this.summary).reduce((a, b) => a + b, 0)
      return this.summary[status] ?? 0
    },

    tagFor(status) {
      return { Active: 'tag-success', Pending: 'tag-accent-2', Cancelled: 'tag-neutral' }[status] ?? 'tag-neutral'
    },

    // ── one-click changes ──────────────────────────────────────────────
    async setRowStatus(row, status) {
      if (status === 'Cancelled') {
        const ok = await this.$store.dialog.confirm({
          title: 'Cancel enrollment',
          message: `${row.student} will lose access to “${row.course}”. Their progress is kept if you approve them again later.`,
          confirmLabel: 'Cancel enrollment',
          cancelLabel: 'Keep it',
          danger: true,
        })
        if (!ok) return
      }

      this.busy = row.id
      try {
        const result = await api.put(`/enrollments/${row.id}`, { status })
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      } finally {
        this.busy = null
      }
    },

    async remove(row) {
      const ok = await this.$store.dialog.confirm({
        title: 'Remove enrollment',
        message: `Remove ${row.student} from “${row.course}” entirely? To only take away access, cancel instead.`,
        confirmLabel: 'Remove',
        danger: true,
      })
      if (!ok) return

      try {
        const result = await api.delete(`/enrollments/${row.id}`)
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      }
    },

    /**
     * Approving or cancelling settles the payment behind it, so the
     * Payments badge (the to-do list) is fetched again.
     */
    async refreshBadge() {
      try {
        const { counts } = await api.get('/dashboard')
        this.$store.ui.counts = { ...this.$store.ui.counts, payments: counts.payments }
      } catch {
        /* the badge catches up on the next load */
      }
    },

    // ── the modal ──────────────────────────────────────────────────────
    openEnroll() {
      this.modal = { mode: 'new', draft: blankEnroll(), errors: {}, message: '', saving: false, password: null }
    },

    openEdit(row) {
      this.modal = {
        mode: 'edit',
        row,
        draft: { status: row.status, amount: row.amount, payment_method: row.payment_method ?? '', transaction_id: row.transaction_id ?? '', note: row.note ?? '' },
        errors: {}, message: '', saving: false,
      }
    },

    closeModal() {
      this.modal = null
    },

    modalError(field) {
      return this.modal?.errors?.[field]?.[0] || ''
    },

    /** Fills the amount from the course's price when one is picked. */
    pickCourse() {
      const course = this.options.courses.find((c) => String(c.id) === String(this.modal.draft.course_id))
      if (course && this.modal.draft.amount === '') this.modal.draft.amount = course.price
    },

    async saveModal() {
      if (!this.modal || this.modal.saving) return
      this.modal.saving = true
      this.modal.errors = {}
      this.modal.message = ''

      try {
        const result = this.modal.mode === 'new'
          ? await api.post('/enrollments', this.modal.draft)
          : await api.put(`/enrollments/${this.modal.row.id}`, this.modal.draft)

        await this.load()
        this.refreshBadge()

        // A new account's password is shown once, in the modal, not a toast.
        if (result.password) {
          this.modal = { mode: 'done', message: result.message, password: result.password }
          return
        }

        this.modal = null
        this.$store.ui.notify(result.message)
      } catch (err) {
        if (!this.modal) return
        this.modal.errors = err.errors || {}
        this.modal.message = Object.keys(err.errors || {}).length ? '' : err.message
      } finally {
        if (this.modal) this.modal.saving = false
      }
    },
  }
}
