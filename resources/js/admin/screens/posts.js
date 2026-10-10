import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resource-table.js'

/** Maps a status onto the tag class the design system defines for it. */
function tagFor(status) {
  if (status === 'Published') return 'tag-accent'
  if (status === 'Scheduled') return 'tag-accent-2'
  return 'tag-neutral'
}

export default function posts() {
  const table = resourceTable({
    endpoint: 'posts',
    label: 'Post',
    trashed: true,
    blank: () => ({
      id: null,
      title: '',
      slug: '',
      excerpt: '',
      body: '',
      status: 'Draft',
      category_id: '',
      user_id: '',
    }),
  })

  // extend(), not spread — spread would flatten the table's getters.
  return extend(table, {
    columns: [
      { key: 'title', label: 'Title', sortable: true },
      { key: 'author', label: 'Author', sortable: false },
      { key: 'category', label: 'Category', sortable: false },
      { key: 'status', label: 'Status', sortable: true },
      { key: 'date', label: 'Date', sortable: true, sortKey: 'published_at' },
    ],

    get statusFilters() {
      return ['All', ...this.options.statuses]
    },

    // Posts are written in a full-page editor, not the generic modal that
    // resourceTable provides for smaller records.
    openCreate() {
      this.$store.ui.go('posts', 'new')
    },

    openEdit(row) {
      this.$store.ui.go('posts', row.id)
    },

    tagFor,
  })
}
