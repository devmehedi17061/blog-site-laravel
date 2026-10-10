import { api } from '../api.js'
import * as tree from '../lib/tree-list.js'

const MAX_DEPTH = 2

function blankCategory() {
  return {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    media_id: null,
    image: null,
    meta_title: '',
    meta_description: '',
    depth: 0,
  }
}

/**
 * Category management.
 *
 * Two kinds of change, deliberately kept apart:
 *
 *   • **One category at a time** — name, slug, description, image, SEO. Too
 *     much to batch, and each save is a single meaningful edit.
 *   • **Order and nesting** — dragging one row changes several, so the whole
 *     tree goes in one request. lib/tree-list.js owns the flat-list-with-depth
 *     shape, the same one Menu control uses.
 *
 * Deleting says out loud what happens to everything attached: posts go where
 * you choose, sub-categories move up a level.
 */
export default function categories() {
  return {
    items: [],
    selectedId: null,
    form: blankCategory(),
    creating: false,

    loading: true,
    saving: false,
    error: '',
    errors: {},

    orderDirty: false,
    savingOrder: false,

    dragIndex: null,
    dragOverIndex: null,

    deleting: null,
    movePostsTo: '',

    maxDepth: MAX_DEPTH,

    async init() {
      await this.load()

      const param = this.$store.ui.param

      if (param) {
        const wanted = this.items.find((c) => String(c.id) === String(param))
        if (wanted) this.select(wanted)
      }

      this.loading = false
    },

    async load() {
      this.error = ''

      try {
        const payload = await api.get('/categories')
        this.adopt(payload.data)
      } catch (err) {
        this.error = err.message
      }
    },

    adopt(rows) {
      this.items = (rows ?? []).map((row) => ({ ...row }))
      this.orderDirty = false

      // Keep whatever was open open, if it still exists.
      const still = this.items.find((c) => c.id === this.selectedId)

      if (still) this.fill(still)
      else if (!this.creating) this.clearSelection()
    },

    // ── selection ──────────────────────────────────────────────────────
    get selected() {
      return this.items.find((c) => c.id === this.selectedId) ?? null
    },

    isSelected(row) {
      return this.selectedId === row.id
    },

    select(row) {
      this.creating = false
      this.selectedId = row.id
      this.errors = {}
      this.fill(row)
      this.$store.ui.go('categories', row.id)
    },

    fill(row) {
      this.form = { ...blankCategory(), ...row }
    },

    clearSelection() {
      this.selectedId = null
      this.creating = false
      this.form = blankCategory()
    },

    /** A new category, optionally already nested under the one that was open. */
    startNew(parentId = null) {
      this.creating = true
      this.selectedId = null
      this.errors = {}
      this.form = { ...blankCategory(), parent_id: parentId }
      this.$store.ui.go('categories')
    },

    get editing() {
      return this.creating || this.selectedId !== null
    },

    // ── the parent dropdown ────────────────────────────────────────────
    /**
     * Categories this one may sit under: not itself, nothing inside it, and
     * nothing already at the deepest level.
     */
    get parentChoices() {
      const id = this.form.id
      const forbidden = new Set()

      if (id) {
        const index = this.items.findIndex((c) => c.id === id)

        if (index >= 0) {
          forbidden.add(id)

          for (let i = index + 1; i <= index + tree.branchLength(this.items, index); i++) {
            forbidden.add(this.items[i].id)
          }
        }
      }

      return this.items.filter((c) => !forbidden.has(c.id) && c.depth < MAX_DEPTH)
    },

    indentLabel(row) {
      return ' '.repeat(row.depth * 3) + (row.depth ? '– ' : '') + row.name
    },

    // ── saving one ─────────────────────────────────────────────────────
    async save() {
      if (this.saving) return

      this.saving = true
      this.error = ''
      this.errors = {}

      const payload = {
        name: this.form.name,
        slug: this.form.slug,
        description: this.form.description,
        media_id: this.form.media_id,
        meta_title: this.form.meta_title,
        meta_description: this.form.meta_description,
        parent_id: this.form.parent_id || null,
      }

      try {
        const result = this.form.id
          ? await api.put(`/categories/${this.form.id}`, payload)
          : await api.post('/categories', payload)

        this.selectedId = result.data.id
        this.creating = false

        await this.load()
        this.$store.ui.go('categories', this.selectedId)
        this.$store.ui.notify(this.form.id ? 'Category saved' : 'Category created')
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message
      } finally {
        this.saving = false
      }
    },

    fieldError(key) {
      return this.errors?.[key]?.[0] || ''
    },

    // ── the image ──────────────────────────────────────────────────────
    async chooseImage() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose a category image' })
      if (!media) return

      this.form.media_id = media.id
      this.form.image = { id: media.id, url: media.url }
    },

    clearImage() {
      this.form.media_id = null
      this.form.image = null
    },

    // ── order and nesting ──────────────────────────────────────────────
    indexOf(row) {
      return this.items.findIndex((c) => c.id === row.id)
    },

    branchLength(index) {
      return tree.branchLength(this.items, index)
    },

    canIndent(index) {
      return tree.canIndent(this.items, index, MAX_DEPTH)
    },

    canOutdent(index) {
      return tree.canOutdent(this.items, index)
    },

    indent(index) {
      if (!this.canIndent(index)) return

      tree.shiftBranch(this.items, index, 1, MAX_DEPTH)
      this.orderDirty = true
    },

    outdent(index) {
      if (!this.canOutdent(index)) return

      tree.shiftBranch(this.items, index, -1, MAX_DEPTH)
      tree.normalise(this.items, MAX_DEPTH)
      this.orderDirty = true
    },

    moveUp(index) {
      tree.moveUp(this.items, index, MAX_DEPTH)
      this.orderDirty = true
    },

    moveDown(index) {
      tree.moveDown(this.items, index, MAX_DEPTH)
      this.orderDirty = true
    },

    onDragStart(index, event) {
      this.dragIndex = index
      event.dataTransfer.effectAllowed = 'move'
      // Firefox will not start a drag without data on the transfer.
      event.dataTransfer.setData('text/plain', String(index))
    },

    onDragOver(index, event) {
      event.preventDefault()

      if (this.dragIndex === null) return

      this.dragOverIndex = tree.dropTarget(this.items, this.dragIndex, index) === null ? null : index
    },

    onDrop(index, event) {
      event.preventDefault()

      if (this.dragIndex !== null) {
        const target = tree.dropTarget(this.items, this.dragIndex, index)

        if (target !== null) {
          tree.moveBranch(this.items, this.dragIndex, target, MAX_DEPTH)
          this.orderDirty = true
        }
      }

      this.onDragEnd()
    },

    onDragEnd() {
      this.dragIndex = null
      this.dragOverIndex = null
    },

    isDropTarget(index) {
      return this.dragOverIndex === index
    },

    async saveOrder() {
      if (this.savingOrder) return

      this.savingOrder = true
      this.error = ''

      try {
        const payload = await api.put('/categories/order', {
          items: this.items.map((c) => ({ id: c.id, depth: c.depth })),
        })

        this.adopt(payload.data)
        this.$store.ui.notify('Order saved')
      } catch (err) {
        this.error = err.message
      } finally {
        this.savingOrder = false
      }
    },

    async discardOrder() {
      await this.load()
    },

    // ── deleting ───────────────────────────────────────────────────────
    askDelete(row) {
      this.deleting = row
      this.movePostsTo = ''
    },

    cancelDelete() {
      this.deleting = null
    },

    /** Everything that would go with the category being deleted. */
    get deleteImpact() {
      if (!this.deleting) return { posts: 0, children: 0 }

      const index = this.indexOf(this.deleting)
      const size = index >= 0 ? tree.branchLength(this.items, index) : 0

      return {
        // Only posts filed directly here move; the sub-categories survive,
        // so what is inside them is not at risk.
        posts: this.deleting.posts_count ?? 0,
        children: size,
        // Only direct children are promoted; deeper ones ride along with them.
        promoted: this.items.filter((c) => c.parent_id === this.deleting.id).length,
      }
    },

    /** Where posts may be moved: anywhere that is not being deleted. */
    get moveChoices() {
      if (!this.deleting) return []

      const index = this.indexOf(this.deleting)
      const doomed = new Set([this.deleting.id])

      for (let i = index + 1; i <= index + tree.branchLength(this.items, index); i++) {
        doomed.add(this.items[i].id)
      }

      return this.items.filter((c) => !doomed.has(c.id))
    },

    async confirmDelete() {
      if (!this.deleting) return

      try {
        const result = await api.delete(
          `/categories/${this.deleting.id}`,
          this.movePostsTo ? { move_posts_to: Number(this.movePostsTo) } : null
        )

        if (this.selectedId === this.deleting.id) this.clearSelection()

        this.deleting = null
        await this.load()
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
        this.deleting = null
      }
    },

    // ── odds and ends ──────────────────────────────────────────────────
    get isEmpty() {
      return !this.loading && this.items.length === 0
    },

    get topLevelCount() {
      return this.items.filter((c) => c.depth === 0).length
    },

    /** What a row says under its name. */
    meta(row) {
      const own = row.posts_count ?? 0
      const branch = row.branch_posts_count ?? own
      const parts = [`/category/${row.slug}`]

      if (branch > own) parts.push(`${own} here · ${branch} in branch`)
      else parts.push(own === 1 ? '1 post' : `${own} posts`)

      return parts.join(' · ')
    },
  }
}
