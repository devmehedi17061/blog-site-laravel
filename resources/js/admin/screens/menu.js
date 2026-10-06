import { api } from '../api.js'
import * as tree from '../lib/treeList.js'

/**
 * Menu control.
 *
 * The menu is edited as a flat list where each row carries its own depth,
 * which lib/treeList.js owns — the same shape the category tree uses. What
 * is dragged here is exactly what gets saved.
 */
export default function menu() {
  return {
    items: [],
    location: 'Header',
    options: {
      locations: ['Header', 'Footer'],
      visibilities: ['Public', 'Authenticated', 'Guests', 'Admin only'],
      targets: ['_self', '_blank'],
      max_depth: 2,
      icons: {},
      links: [],
    },

    activeIndex: null,
    dragIndex: null,
    dragOverIndex: null,

    loading: true,
    saving: false,
    error: '',
    errors: {},
    dirty: false,

    iconPickerFor: null,
    iconSearch: '',
    linkPickerFor: null,

    async init() {
      try {
        this.options = await api.get('/menu/options')
      } catch {
        /* the builder falls back to its own lists */
      }

      // `#/menu/Footer` opens straight on that menu.
      const param = this.$store.ui.param

      if (this.options.locations.includes(param)) this.location = param

      await this.load()
      this.loading = false
    },

    async load() {
      this.error = ''

      try {
        const payload = await api.get(`/menu?location=${encodeURIComponent(this.location)}`)
        this.adopt(payload.data)
      } catch (err) {
        this.error = err.message
      }
    },

    adopt(rows) {
      this.items = (rows ?? []).map((row) => ({ ...row }))
      this.activeIndex = this.items.length ? 0 : null
      this.errors = {}
      this.dirty = false
    },

    async switchLocation(location) {
      if (this.dirty && !(await this.$store.dialog.confirm({
        title: 'Discard changes',
        message: 'This menu has unsaved changes. Switching menus will throw them away.',
        confirmLabel: 'Discard changes',
        cancelLabel: 'Keep editing',
        danger: true,
      }))) return

      this.location = location
      this.$store.ui.go('menu', location)
      await this.load()
    },

    touch() {
      this.dirty = true
    },

    // ── the list ───────────────────────────────────────────────────────
    get active() {
      return this.activeIndex === null ? null : (this.items[this.activeIndex] ?? null)
    },

    select(index) {
      this.activeIndex = index
    },

    isActive(index) {
      return this.activeIndex === index
    },

    branchLength(index) {
      return tree.branchLength(this.items, index)
    },

    hasChildren(index) {
      return this.branchLength(index) > 0
    },

    // ── adding and removing ────────────────────────────────────────────
    addItem() {
      const at = this.activeIndex === null
        ? this.items.length
        : this.activeIndex + 1 + this.branchLength(this.activeIndex)

      this.items.splice(at, 0, {
        id: null,
        label: 'New item',
        url: '/',
        icon: null,
        target: '_self',
        visibility: 'Public',
        // A new row lands beside the one it was added from, never inside it.
        depth: this.items[this.activeIndex]?.depth ?? 0,
      })

      this.activeIndex = at
      this.dirty = true
    },

    duplicateItem(index) {
      const size = this.branchLength(index)

      // The branch comes too, with its ids cleared so it saves as new rows.
      const copy = this.items.slice(index, index + size + 1).map((item, i) => ({
        ...JSON.parse(JSON.stringify(item)),
        id: null,
        label: i === 0 ? `${item.label} copy` : item.label,
      }))

      this.items.splice(index + size + 1, 0, ...copy)
      this.activeIndex = index + size + 1
      this.dirty = true
    },

    async removeItem(index) {
      const size = this.branchLength(index)

      if (size > 0) {
        const what = size === 1 ? 'its sub-item' : `its ${size} sub-items`
        const confirmed = await this.$store.dialog.confirm({
          title: 'Remove menu item',
          message: `Remove "${this.items[index].label}" and ${what}? Nothing is lost until you save the menu.`,
          confirmLabel: 'Remove',
          danger: true,
        })
        if (!confirmed) return
      }

      this.items.splice(index, size + 1)
      this.activeIndex = this.items.length ? Math.min(index, this.items.length - 1) : null
      this.dirty = true
    },

    // ── nesting ────────────────────────────────────────────────────────
    canIndent(index) {
      return tree.canIndent(this.items, index, this.options.max_depth)
    },

    canOutdent(index) {
      return tree.canOutdent(this.items, index)
    },

    /** Indenting takes the branch with it, so the shape below stays intact. */
    indent(index) {
      if (!this.canIndent(index)) return

      tree.shiftBranch(this.items, index, 1, this.options.max_depth)
      this.dirty = true
    },

    outdent(index) {
      if (!this.canOutdent(index)) return

      tree.shiftBranch(this.items, index, -1, this.options.max_depth)
      this.normalise()
      this.dirty = true
    },

    normalise() {
      tree.normalise(this.items, this.options.max_depth)
    },

    // ── moving ─────────────────────────────────────────────────────────
    moveUp(index) {
      this.activeIndex = tree.moveUp(this.items, index, this.options.max_depth)
      this.dirty = true
    },

    moveDown(index) {
      this.activeIndex = tree.moveDown(this.items, index, this.options.max_depth)
      this.dirty = true
    },

    moveBranch(from, to) {
      this.activeIndex = tree.moveBranch(this.items, from, to, this.options.max_depth)
      this.dirty = true
    },

    // ── dragging ───────────────────────────────────────────────────────
    onDragStart(index, event) {
      this.dragIndex = index
      this.activeIndex = index

      // Firefox will not start a drag without data on the transfer.
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData('text/plain', String(index))
    },

    onDragOver(index, event) {
      event.preventDefault()

      if (this.dragIndex === null) return

      // Null means the drop would put the branch inside itself.
      this.dragOverIndex = tree.dropTarget(this.items, this.dragIndex, index) === null ? null : index
    },

    onDrop(index, event) {
      event.preventDefault()

      if (this.dragIndex === null || this.dragOverIndex === null) return this.onDragEnd()

      const target = tree.dropTarget(this.items, this.dragIndex, index)

      if (target !== null) this.moveBranch(this.dragIndex, target)
      this.onDragEnd()
    },

    onDragEnd() {
      this.dragIndex = null
      this.dragOverIndex = null
    },

    isDropTarget(index) {
      return this.dragOverIndex === index
    },

    // ── icons ──────────────────────────────────────────────────────────
    get iconNames() {
      const term = this.iconSearch.trim().toLowerCase()
      const names = Object.keys(this.options.icons ?? {})

      return term ? names.filter((name) => name.includes(term)) : names
    },

    /** The svg body for a name, straight from the map the site also reads. */
    iconBody(name) {
      return this.options.icons?.[name] ?? ''
    },

    iconSvg(name, size = 16) {
      const body = this.iconBody(name)

      if (!body) return ''

      return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none"`
        + ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round"'
        + ` stroke-linejoin="round" aria-hidden="true">${body}</svg>`
    },

    openIconPicker(index) {
      this.iconSearch = ''
      this.iconPickerFor = index
    },

    chooseIcon(name) {
      if (this.iconPickerFor === null) return

      this.items[this.iconPickerFor].icon = name
      this.iconPickerFor = null
      this.dirty = true
    },

    clearIcon(index) {
      this.items[index].icon = null
      this.dirty = true
    },

    // ── link picker ────────────────────────────────────────────────────
    openLinkPicker(index) {
      this.linkPickerFor = index
    },

    chooseLink(url) {
      if (this.linkPickerFor === null) return

      this.items[this.linkPickerFor].url = url
      this.linkPickerFor = null
      this.dirty = true
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save() {
      if (this.saving) return

      this.saving = true
      this.error = ''
      this.errors = {}
      this.normalise()

      try {
        const payload = await api.put('/menu', {
          location: this.location,
          items: this.items.map((item) => ({
            id: item.id,
            label: item.label,
            url: item.url,
            icon: item.icon,
            target: item.target,
            visibility: item.visibility,
            depth: item.depth,
          })),
        })

        this.adopt(payload.data)
        this.$store.ui.notify(`${this.location} menu saved`)
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some items need attention.' : err.message
      } finally {
        this.saving = false
      }
    },

    errorFor(index) {
      const key = Object.keys(this.errors).find((k) => k.startsWith(`items.${index}.`))
      return key ? this.errors[key][0] : ''
    },

    // ── odds and ends ──────────────────────────────────────────────────
    get isEmpty() {
      return !this.loading && this.items.length === 0
    },

    get topLevelCount() {
      return this.items.filter((item) => item.depth === 0).length
    },

    /** What the row says under its label. */
    meta(item) {
      const parts = [item.url || '/']

      if (item.target === '_blank') parts.push('new tab')
      if (item.visibility !== 'Public') parts.push(item.visibility.toLowerCase())

      return parts.join(' · ')
    },

    get footerNote() {
      return this.location === 'Footer'
        ? 'The footer prints one row, so sub-items are kept but not shown there.'
        : 'Sub-items open as a dropdown; a third level flies out beside it.'
    },
  }
}
