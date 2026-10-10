import { api } from '../api.js'
import { debounce } from '../lib/debounce.js'

/** Reasons offered when rejecting; any other can be typed. */
const REASONS = [
  'The transaction ID was not found in our account.',
  'The amount received is less than the course price.',
  'This transaction ID was already used for another payment.',
  'The screenshot does not match the details given.',
]

/**
 * LMS → Payments: check what students reported and verify or reject it.
 * Waiting payments come first, oldest first.
 */
export default function payments() {
  return {
    rows: [],
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
    summary: { Pending: 0, Verified: 0, Rejected: 0 },
    verifiedTotal: '',
    methods: [],
    query: { search: '', status: 'Pending', method: '', per_page: 10, page: 1 },
    loading: true,
    error: '',
    busy: null,
    rejecting: null,
    preview: null,
    selected: [],
    reasons: REASONS,

    async init() {
      if (this.$store.ui.param === 'all') this.query.status = 'All'
      this.searchDebounced = debounce(() => this.applyFilters(), 350)
      await this.load()
    },

    async load() {
      this.loading = true
      this.error = ''
      try {
        const query = { ...this.query }
        if (query.status === 'All') delete query.status
        if (!query.method) delete query.method
        const payload = await api.get(`/payments?${new URLSearchParams(query)}`)
        this.rows = payload.data
        this.meta = payload.meta
        this.summary = payload.summary
        this.verifiedTotal = payload.verified_total
        this.methods = payload.methods
      } catch (err) {
        this.error = err.message
        this.rows = []
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
      return total ? `Showing ${from}–${to} of ${total}` : 'No payments'
    },

    countFor(status) {
      if (status === 'All') return Object.values(this.summary).reduce((a, b) => a + b, 0)
      return this.summary[status] ?? 0
    },

    /** Things worth a second look before verifying. */
    flags(row) {
      const flags = []
      if (row.duplicates.length) flags.push(`Transaction ID also used on ${row.duplicates.map((d) => `${d.invoice} (${d.student}, ${d.status})`).join(', ')}`)
      if (row.short) flags.push(`Paid ${row.amount_label}, the course costs ${row.expected_label}`)
      if (!row.proof_url) flags.push('No screenshot attached')
      return flags
    },

    replace(row) {
      const i = this.rows.findIndex((r) => r.id === row.id)
      if (i >= 0) this.rows.splice(i, 1, row)
    },

    refreshBadge() {
      this.$store.ui.counts = { ...this.$store.ui.counts, payments: this.summary.Pending || undefined }
    },

    async verify(row) {
      if (row.duplicates.length || row.short) {
        const ok = await this.$store.dialog.confirm({
          title: 'Verify anyway?',
          message: this.flags(row).filter((f) => !f.startsWith('No screenshot')).join('\n'),
          confirmLabel: 'Verify payment',
          danger: true,
        })
        if (!ok) return
      }

      this.busy = row.id
      try {
        const result = await api.post(`/payments/${row.id}/verify`)
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      } finally {
        this.busy = null
      }
    },

    openReject(row) {
      this.rejecting = { row, reason: row.duplicates.length ? REASONS[2] : (row.short ? REASONS[1] : ''), error: '', saving: false }
    },

    async confirmReject() {
      if (!this.rejecting || this.rejecting.saving) return
      this.rejecting.saving = true
      this.rejecting.error = ''
      try {
        const result = await api.post(`/payments/${this.rejecting.row.id}/reject`, { reason: this.rejecting.reason })
        this.rejecting = null
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        if (this.rejecting) {
          this.rejecting.error = err.errors?.reason?.[0] ?? err.message
          this.rejecting.saving = false
        }
      }
    },

    // ── several at once ────────────────────────────────────────────────
    get pendingRows() {
      return this.rows.filter((r) => r.status === 'Pending' && r.can.review)
    },

    toggleSelect(id) {
      const i = this.selected.indexOf(id)
      i >= 0 ? this.selected.splice(i, 1) : this.selected.push(id)
    },

    get allSelected() {
      return this.pendingRows.length > 0 && this.pendingRows.every((r) => this.selected.includes(r.id))
    },

    toggleAll() {
      this.selected = this.allSelected ? [] : this.pendingRows.map((r) => r.id)
    },

    async verifySelected() {
      const picked = this.rows.filter((r) => this.selected.includes(r.id))
      const flagged = picked.filter((r) => r.duplicates.length || r.short)
      const ok = await this.$store.dialog.confirm({
        title: `Verify ${picked.length} payment${picked.length === 1 ? '' : 's'}`,
        message: flagged.length
          ? `${flagged.length} of them ${flagged.length === 1 ? 'has' : 'have'} a warning (short amount or a reused transaction ID): ${flagged.map((r) => r.invoice).join(', ')}.
Verify them all anyway?`
          : 'Each course opens and each student is notified.',
        confirmLabel: 'Verify all',
        danger: flagged.length > 0,
      })
      if (!ok) return

      try {
        const result = await api.post('/payments/bulk-verify', { ids: this.selected })
        this.selected = []
        this.$store.ui.notify(result.message)
        await this.load()
        this.refreshBadge()
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      }
    },

    /** The list as filtered now, as a spreadsheet. */
    exportCsv() {
      const query = { ...this.query }
      delete query.page
      delete query.per_page
      if (query.status === 'All') delete query.status
      window.location.href = `/admin/api/payments/export?${new URLSearchParams(query)}`
    },

    copy(text) {
      navigator.clipboard?.writeText(text)
      this.$store.ui.notify(`Copied ${text}`)
    },
  }
}
