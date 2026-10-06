import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resourceTable.js'

function tagFor(status) {
  if (status === 'Published') return 'tag-accent'
  if (status === 'Scheduled') return 'tag-accent-2'
  return 'tag-neutral'
}

export default function pages() {
  const table = resourceTable({
    endpoint: 'pages',
    label: 'Page',
    blank: () => ({ id: null, title: '', slug: '', status: 'Draft', template: 'blank' }),
  })

  const baseInit = table.init
  const baseLoadOptions = table.loadOptions

  // extend(), not spread — spread would flatten the table's getters.
  return extend(table, {
    columns: [
      { key: 'title', label: 'Title', sortable: true },
      { key: 'slug', label: 'Address', sortable: false },
      { key: 'status', label: 'Status', sortable: true },
      { key: 'layout', label: 'Layout', sortable: false },
      { key: 'sections_count', label: 'Sections', sortable: false },
      { key: 'updated', label: 'Updated', sortable: true, sortKey: 'created_at' },
    ],

    // The template chooser lives in the list; picking one creates the page
    // already filled, then hands straight over to the editor.
    choosing: false,

    // What "/" shows, and the published pages that could take its place.
    home: { page_id: null, shows: '', candidates: [] },
    homeSaving: false,

    async init() {
      await Promise.all([baseInit.call(this), this.loadHome()])
    },

    async loadOptions() {
      await baseLoadOptions.call(this)
      this.options.layouts ??= []
    },

    async loadHome() {
      try {
        this.home = await api.get('/pages/homepage')
      } catch {
        /* the bar just stays empty */
      }
    },

    get statusFilters() {
      return ['All', ...this.options.statuses]
    },

    layoutLabel(key) {
      return this.options.layouts?.find((l) => l.value === key)?.label ?? 'Standard'
    },

    // ── the homepage ───────────────────────────────────────────────────
    /** From the select above the table: a page id, or "" for the blog. */
    async chooseHome(value) {
      const id = value === '' ? null : Number(value)
      if (id === this.home.page_id) return

      const page = this.home.candidates.find((c) => c.id === id)
      const ok = await this.$store.dialog.confirm({
        title: 'Change the homepage',
        message: page
          ? `Visitors to ${window.location.host}/ will see "${page.title}". Its own address will redirect there.`
          : `Visitors to ${window.location.host}/ will see the built-in course homepage (featured courses and latest posts) instead of a page.`,
        confirmLabel: 'Change homepage',
      })

      if (!ok) {
        // Put the select back on what is really the homepage.
        this.home = { ...this.home }
        return
      }

      await this.saveHome(id)
    },

    async makeHome(row) {
      const ok = await this.$store.dialog.confirm({
        title: 'Set as homepage',
        message: `Visitors to ${window.location.host}/ will see "${row.title}" instead of "${this.home.shows}". Its own address, ${row.slug}, will redirect to /.`,
        confirmLabel: 'Set as homepage',
      })
      if (ok) await this.saveHome(row.id)
    },

    async saveHome(id) {
      this.homeSaving = true

      try {
        const result = await api.put('/pages/homepage', { page_id: id })
        this.home = result
        this.$store.ui.notify(result.message)
        await this.load()
      } catch (err) {
        this.error = err.message
        this.home = { ...this.home }
      } finally {
        this.homeSaving = false
      }
    },

    // ── rows ───────────────────────────────────────────────────────────
    async duplicate(row) {
      try {
        const result = await api.post(`/pages/${row.id}/duplicate`)
        this.$store.ui.notify(`Copied as "${result.data.title}" — a draft`)
        this.$store.ui.go('pages', result.data.id)
      } catch (err) {
        this.error = err.message
      }
    },

    /** Live pages open where visitors see them; anything else as a preview. */
    viewUrl(row) {
      return row.is_live ? row.public_url : row.preview_url
    },

    async confirmDelete() {
      if (!this.confirming) return

      const row = this.confirming
      this.confirming = null

      try {
        await api.delete(`/pages/${row.id}`)
        this.$store.ui.notify('Page moved to the trash')
        await Promise.all([this.load(), this.loadHome()])
      } catch (err) {
        this.error = err.message
      }
    },

    openCreate() {
      this.choosing = true
    },

    cancelCreate() {
      this.choosing = false
    },

    async createFrom(template) {
      this.choosing = false
      this.$store.ui.go('pages', `new:${template}`)
    },

    openEdit(row) {
      this.$store.ui.go('pages', row.id)
    },

    openBuilder(row) {
      this.$store.ui.go('sections', row.id)
    },

    tagFor,
  })
}
