import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

/**
 * The form inbox: everything every form has been sent, in one list.
 *
 * Laid out the way a mail client is — a list on the left, the message on the
 * right — because that is the shape people already know for this job. Opening
 * a row marks it read, which is what keeps the sidebar badge honest.
 */
export default function inbox() {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    summary: { total: 0, unread: 0, forms: [] },

    open: null,
    openLoading: false,
    selected: [],

    loading: true,
    error: '',

    query: {
      search: '',
      form: 'all',
      state: 'All',
      per_page: 20,
      page: 1,
    },

    async init() {
      this.searchDebounced = debounce(() => this.applyFilters(), 350)

      // `#/inbox/12` opens straight onto one submission, so a link from
      // anywhere else in the panel lands on the right message.
      const param = this.$store.ui.param

      await this.load()

      if (param) await this.openRow({ id: Number(param) })
    },

    // ── loading ────────────────────────────────────────────────────────
    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get(`/inbox?${new URLSearchParams(this.query)}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.adoptSummary(payload.summary)

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

    /** The badge and the filter counts come back with every write. */
    adoptSummary(summary) {
      if (!summary) return

      this.summary = summary
      this.$store.ui.counts = { ...this.$store.ui.counts, inbox: summary.unread }
    },

    applyFilters() {
      this.query.page = 1
      this.selected = []
      this.load()
    },

    setForm(id) {
      this.query.form = String(id)
      this.applyFilters()
    },

    setState(state) {
      this.query.state = state
      this.applyFilters()
    },

    goToPage(page) {
      if (page < 1 || page > this.meta.last_page) return

      this.query.page = page
      this.selected = []
      this.load()
    },

    get isEmpty() {
      return !this.loading && this.rows.length === 0
    },

    get resultCount() {
      if (!this.meta.total) return 'No submissions'
      return `${this.meta.from}–${this.meta.to} of ${this.meta.total}`
    },

    get states() {
      return ['All', 'Unread', 'Read']
    },

    // ── reading one ────────────────────────────────────────────────────
    async openRow(row) {
      this.openLoading = true

      try {
        const payload = await api.get(`/inbox/${row.id}`)
        this.open = payload.data

        // The server marked it read; reflect that in the list without a
        // second round trip, and take one off the badge.
        const listed = this.rows.find((r) => r.id === row.id)

        if (listed?.unread) {
          listed.unread = false
          this.summary = { ...this.summary, unread: Math.max(0, this.summary.unread - 1) }
          this.$store.ui.counts = { ...this.$store.ui.counts, inbox: this.summary.unread }
          this.bumpFormUnread(listed.form?.id, -1)
        }

        this.$store.ui.go('inbox', row.id)
      } catch (err) {
        this.error = err.message
      } finally {
        this.openLoading = false
      }
    },

    closeRow() {
      this.open = null
      this.$store.ui.go('inbox')
    },

    isOpen(row) {
      return this.open?.id === row.id
    },

    bumpFormUnread(formId, by) {
      this.summary = {
        ...this.summary,
        forms: (this.summary.forms ?? []).map((form) =>
          form.id === formId ? { ...form, unread: Math.max(0, form.unread + by) } : form
        ),
      }
    },

    async toggleRead(row) {
      try {
        const payload = await api.put(`/inbox/${row.id}`, { unread: !row.unread })
        const updated = payload.data

        const listed = this.rows.find((r) => r.id === row.id)
        if (listed) listed.unread = updated.unread
        if (this.open?.id === row.id) this.open = { ...this.open, unread: updated.unread }

        await this.refreshCounts()
      } catch (err) {
        this.error = err.message
      }
    },

    // ── acting on many ─────────────────────────────────────────────────
    toggleSelected(id) {
      this.selected = this.selected.includes(id)
        ? this.selected.filter((x) => x !== id)
        : [...this.selected, id]
    },

    isSelected(id) {
      return this.selected.includes(id)
    },

    get allSelected() {
      return this.rows.length > 0 && this.selected.length === this.rows.length
    },

    toggleAll() {
      this.selected = this.allSelected ? [] : this.rows.map((row) => row.id)
    },

    async bulk(action) {
      if (this.selected.length === 0) return

      if (action === 'delete') {
        const count = this.selected.length
        const what = count === 1 ? 'this submission' : `these ${count} submissions`

        const confirmed = await this.$store.dialog.confirm({
          title: count === 1 ? 'Delete submission' : 'Delete submissions',
          message: `Delete ${what}? This cannot be undone.`,
          confirmLabel: 'Delete',
          danger: true,
        })
        if (!confirmed) return
      }

      try {
        const result = await api.post('/inbox/bulk', { action, ids: this.selected })
        this.adoptSummary(result.summary)

        if (action === 'delete' && this.selected.includes(this.open?.id)) this.open = null

        this.selected = []
        await this.load()
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    async deleteRow(row) {
      const confirmed = await this.$store.dialog.confirm({
        title: 'Delete submission',
        message: 'Delete this submission? This cannot be undone.',
        confirmLabel: 'Delete',
        danger: true,
      })
      if (!confirmed) return

      try {
        const result = await api.delete(`/inbox/${row.id}`)
        this.adoptSummary(result.summary)

        if (this.open?.id === row.id) this.open = null

        await this.load()
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    async markAllRead() {
      try {
        const result = await api.post('/inbox/read-all', {
          form: this.query.form,
          search: this.query.search,
        })

        this.adoptSummary(result.summary)
        await this.load()
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    /** Re-reads just the counts, after a change that did not reload the list. */
    async refreshCounts() {
      try {
        const payload = await api.get(`/inbox?${new URLSearchParams({ ...this.query, per_page: 5 })}`)
        this.adoptSummary(payload.summary)
      } catch {
        /* the badge is not worth an error message */
      }
    },

    // ── odds and ends ──────────────────────────────────────────────────
    get currentFormName() {
      if (this.query.form === 'all') return 'All forms'

      return this.summary.forms?.find((f) => String(f.id) === String(this.query.form))?.name ?? 'All forms'
    },

    get exportUrl() {
      return this.query.form === 'all'
        ? null
        : `/admin/api/forms/${this.query.form}/submissions/export`
    },

    openForm(row) {
      if (row.form?.id) this.$store.ui.go('forms', row.form.id)
    },
  }
}
