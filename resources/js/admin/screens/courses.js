import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resourceTable.js'

/** The list of courses. Editing happens in the full-page course editor. */
export default function courses() {
  const table = resourceTable({
    endpoint: 'courses',
    label: 'Course',
    trashed: true,
    blank: () => ({ id: null, title: '', status: 'Draft' }),
  })

  // extend(), not spread — spread would flatten the table's getters.
  return extend(table, {
    columns: [
      { key: 'title', label: 'Course', sortable: true },
      { key: 'instructor', label: 'Instructor', sortable: false },
      { key: 'lessons_count', label: 'Lessons', sortable: false },
      { key: 'students_count', label: 'Students', sortable: false },
      { key: 'price', label: 'Price', sortable: true },
      { key: 'status', label: 'Status', sortable: true },
    ],

    get statusFilters() {
      return ['All', ...(this.options.statuses ?? [])]
    },

    openCreate() {
      this.$store.ui.go('courses', 'new')
    },

    openEdit(row) {
      this.$store.ui.go('courses', row.id)
    },

    tagFor(status) {
      return status === 'Published' ? 'tag-accent' : 'tag-neutral'
    },
  })
}
