import { api } from '../api.js'

/** Maps the API's tone keyword onto the stat-tile colour pairs. */
const TONES = {
  accent: { bg: 'var(--color-accent)', fg: '#ffffff', soft: 'rgba(255,255,255,0.85)' },
  dark: { bg: 'var(--color-neutral-900)', fg: '#ffffff', soft: 'var(--color-accent-300)' },
  tint: { bg: 'var(--color-accent-100)', fg: 'var(--color-accent-900)', soft: 'var(--color-accent-700)' },
  plain: { bg: 'var(--color-surface)', fg: 'var(--color-ink)', soft: 'var(--color-neutral-600)' },
}

/** One colour per chart series, readable on white and in a legend. */
const SERIES_COLORS = { posts: 'var(--color-accent)', messages: 'var(--color-neutral-900)', uploads: 'var(--color-accent-300)' }

const STATUS_COLORS = { Published: 'var(--color-accent)', Scheduled: 'var(--color-accent-300)', Draft: 'var(--color-neutral-400)' }

// Chart geometry, in SVG units (the SVG scales to its box).
const CHART = { width: 720, height: 216, top: 12, bottom: 36, left: 28, right: 8 }

export default function dashboard() {
  return {
    loading: true,
    error: '',
    loadedAt: null,
    greeting: null,
    site: null,
    stats: [],
    trend: { days: [], series: [] },
    attention: [],
    content: { posts: null, pages: null, categories: [], uncategorised: 0 },
    seo: [],
    upcoming: [],
    recent: [],
    messages: [],
    activity: [],
    system: [],

    // chart state
    hidden: [],
    hover: null,

    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get('/dashboard')

        this.greeting = payload.greeting
        this.site = payload.site
        this.stats = (payload.stats || []).map((stat) => ({ ...stat, ...(TONES[stat.tone] || TONES.plain) }))
        this.trend = payload.trend || { days: [], series: [] }
        this.attention = payload.attention || []
        this.content = payload.content || this.content
        this.seo = payload.seo || []
        this.upcoming = payload.upcoming || []
        this.recent = payload.recent || []
        this.messages = payload.messages || []
        this.activity = payload.activity || []
        this.system = payload.system || []
        this.loadedAt = new Date()

        this.$store.ui.counts = payload.counts || {}
      } catch (error) {
        this.error = error.message
      } finally {
        this.loading = false
      }
    },

    open(screen, param = null) {
      if (screen) this.$store.ui.go(screen, param)
    },

    get updatedLabel() {
      return this.loadedAt ? `Updated ${this.loadedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''
    },

    // ── the 30-day chart ───────────────────────────────────────────────
    seriesColor(key) {
      return SERIES_COLORS[key] ?? 'var(--color-neutral-600)'
    },

    toggleSeries(key) {
      this.hidden = this.hidden.includes(key) ? this.hidden.filter((k) => k !== key) : [...this.hidden, key]
    },

    get shownSeries() {
      return this.trend.series.filter((s) => !this.hidden.includes(s.key))
    },

    seriesTotal(key) {
      return this.trend.days.reduce((sum, day) => sum + (day[key] ?? 0), 0)
    },

    /** Round the top of the scale up to a tidy number, never below 4. */
    get chartMax() {
      const peak = Math.max(0, ...this.trend.days.flatMap((day) => this.shownSeries.map((s) => day[s.key] ?? 0)))
      if (peak <= 4) return 4
      const step = 10 ** Math.floor(Math.log10(peak))
      return Math.ceil(peak / step) * step
    },

    get chart() {
      const { width, height, top, bottom, left, right } = CHART
      const days = this.trend.days
      const series = this.shownSeries
      const plotW = width - left - right
      const plotH = height - top - bottom
      const slot = days.length ? plotW / days.length : plotW
      const gap = Math.max(1.5, slot * 0.22)
      const barW = series.length ? Math.max(1, (slot - gap) / series.length) : 0
      const max = this.chartMax
      const y = (v) => top + plotH - (v / max) * plotH

      const bars = []
      days.forEach((day, i) => {
        series.forEach((s, j) => {
          const value = day[s.key] ?? 0
          if (!value) return
          bars.push({
            id: `${day.date}-${s.key}`,
            x: left + i * slot + gap / 2 + j * barW,
            y: y(value),
            w: Math.max(1, barW - 0.6),
            h: Math.max(1, (value / max) * plotH),
            color: this.seriesColor(s.key),
          })
        })
      })

      const grid = [0, 0.25, 0.5, 0.75, 1].map((f) => ({ y: y(max * f), label: Math.round(max * f) }))
      // A date under every fifth day and the last one, so labels never collide.
      const labels = days
        .map((day, i) => ({ x: left + i * slot + slot / 2, text: day.label, i }))
        .filter((l) => l.i % 5 === 0 || l.i === days.length - 1)
      const hits = days.map((day, i) => ({ i, x: left + i * slot, w: slot }))

      return { width, height, top, plotH, left, bars, grid, labels, hits, bottomY: top + plotH }
    },

    /**
     * The chart as SVG markup. Built as a string because Alpine's x-for
     * cannot run inside an <svg>; the values are all numbers and dates we
     * produced, never user text.
     */
    get chartSvg() {
      const c = this.chart
      const right = c.width - CHART.right
      const grid = c.grid.map((g) =>
        `<line x1="${c.left}" x2="${right}" y1="${g.y}" y2="${g.y}" style="stroke:var(--color-line)" stroke-width="1"/>`
        + `<text x="${c.left - 6}" y="${g.y + 3.5}" text-anchor="end" class="dash-axis">${g.label}</text>`).join('')
      const hover = this.hover === null ? '' : (() => {
        const hit = c.hits[this.hover]
        return `<rect x="${hit.x}" y="${c.top}" width="${hit.w}" height="${c.plotH}" style="fill:var(--color-accent-100)"/>`
      })()
      const bars = c.bars.map((b) => `<rect x="${b.x}" y="${b.y}" width="${b.w}" height="${b.h}" style="fill:${b.color}"/>`).join('')
      const labels = c.labels.map((l) => `<text x="${l.x}" y="${c.bottomY + 17}" text-anchor="middle" class="dash-axis">${l.text}</text>`).join('')
      const base = `<line x1="${c.left}" x2="${right}" y1="${c.bottomY}" y2="${c.bottomY}" style="stroke:var(--color-line-strong)" stroke-width="1"/>`

      return hover + grid + bars + base + labels
    },

    /** Where the hover strips sit over the plot, as percentages of the chart box. */
    get hoverStyle() {
      const { width, left, right, top, height, bottom } = CHART
      return `left:${(left / width) * 100}%;right:${(right / width) * 100}%;top:${(top / height) * 100}%;bottom:${(bottom / height) * 100}%`
    },

    hoverAt(event) {
      const box = event.currentTarget.getBoundingClientRect()
      const count = this.trend.days.length
      if (!count || !box.width) return
      this.hover = Math.max(0, Math.min(count - 1, Math.floor(((event.clientX - box.left) / box.width) * count)))
    },

    get hovered() {
      return this.hover === null ? null : this.trend.days[this.hover]
    },

    // ── content blocks ─────────────────────────────────────────────────
    statusColor(status) {
      return STATUS_COLORS[status] ?? 'var(--color-neutral-400)'
    },

    total(rows) {
      return (rows ?? []).reduce((sum, row) => sum + row.count, 0)
    },

    share(count, rows) {
      const all = this.total(rows)
      return all ? (count / all) * 100 : 0
    },

    get categoryMax() {
      return Math.max(1, ...this.content.categories.map((c) => c.count))
    },

    percent(row) {
      return row.total ? Math.round((row.have / row.total) * 100) : 100
    },

    percentTone(row) {
      const pct = this.percent(row)
      return pct >= 80 ? 'good' : pct >= 40 ? 'fair' : 'poor'
    },

    get seoScore() {
      const rows = this.seo.filter((row) => row.total)
      if (!rows.length) return null
      return Math.round(rows.reduce((sum, row) => sum + this.percent(row), 0) / rows.length)
    },

    get warnCount() {
      return this.attention.filter((item) => item.tone === 'warn').length
    },

    tagFor(status) {
      if (status === 'Published') return 'tag-accent'
      if (status === 'Scheduled') return 'tag-accent-2'
      return 'tag-neutral'
    },
  }
}
