import { api } from '../api.js'

/**
 * LMS → Reports. Charts are plain HTML bars: one series each, one hue, a
 * tooltip on hover, a label only on the highest and the latest month, and
 * a table view of the same numbers.
 */
export default function reports() {
  return {
    data: null,
    loading: true,
    error: '',
    asTable: { revenue: false, enrollments: false },
    tip: null,

    async init() {
      try {
        this.data = await api.get('/reports')
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    max(key) {
      return Math.max(1, ...(this.data?.months ?? []).map((m) => m[key]))
    },

    /** Bar height in %, never zero-height for a real value. */
    height(month, key) {
      const v = month[key]
      return v ? Math.max(3, (v / this.max(key)) * 100) : 0
    },

    /** Direct labels: the highest month and the latest one, nothing else. */
    labelled(month, index, key) {
      const months = this.data.months
      if (!month[key]) return false
      const top = months.reduce((best, m) => (m[key] > best[key] ? m : best), months[0])
      return month.key === top.key || index === months.length - 1
    },

    show(event, text) {
      const box = event.currentTarget.closest('.rp-chart').getBoundingClientRect()
      const bar = event.currentTarget.getBoundingClientRect()
      this.tip = { text, x: bar.left - box.left + bar.width / 2, y: 0, chart: event.currentTarget.closest('.rp-chart').dataset.chart }
    },

    hide() {
      this.tip = null
    },

    widthOf(value, list, key) {
      const top = Math.max(1, ...list.map((x) => x[key] ?? 0))
      return `${Math.max(2, ((value ?? 0) / top) * 100)}%`
    },

    download(path) {
      window.location.href = `/admin/api/${path}`
    },
  }
}
