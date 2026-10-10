import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resource-table.js'

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

    /** A draft copy — curriculum, quizzes and videos included — opened for editing. */
    async duplicate(row) {
      try {
        const result = await api.post(`/courses/${row.id}/duplicate`)
        this.$store.ui.notify(result.message)
        this.$store.ui.go('courses', result.id)
      } catch (err) {
        this.$store.ui.notify(err.message, 'warn')
      }
    },

    tagFor(status) {
      return status === 'Published' ? 'tag-accent' : 'tag-neutral'
    },
  })
}
