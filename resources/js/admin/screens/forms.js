import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { resourceTable } from '../lib/resource-table.js'

export default function forms() {
  const table = resourceTable({
    endpoint: 'forms',
    label: 'Form',
    blank: () => ({ id: null, name: '', status: 'Active' }),
  })

  // extend(), not spread — spread would flatten the table's getters.
  return extend(table, {
    columns: [
      { key: 'name', label: 'Form', sortable: true },
      { key: 'slug', label: 'Address', sortable: false },
      { key: 'status', label: 'Status', sortable: true },
      { key: 'fields_count', label: 'Fields', sortable: false },
      { key: 'submissions_count', label: 'Replies', sortable: false },
      { key: 'updated', label: 'Updated', sortable: true, sortKey: 'updated_at' },
    ],

    creating: false,
    newName: '',
    createError: '',

    get statusFilters() {
      return ['All', ...(this.options.statuses ?? [])]
    },

    tagFor(status) {
      return status === 'Active' ? 'tag-accent' : 'tag-neutral'
    },

    openCreate() {
      this.newName = ''
      this.createError = ''
      this.creating = true
    },

    cancelCreate() {
      this.creating = false
    },

    /**
     * A form is created with a name and nothing else, then handed straight to
     * the builder — there is no useful "new form" screen to fill in first.
     */
    async create() {
      const name = this.newName.trim()

      if (!name) {
        this.createError = 'Give the form a name.'
        return
      }

      try {
        const result = await api.post('/forms', { name, status: 'Active', fields: [] })
        this.creating = false
        this.$store.ui.go('forms', result.data.id)
      } catch (err) {
        this.createError = err.errors?.name?.[0] || err.message
      }
    },

    openBuilder(row) {
      this.$store.ui.go('forms', row.id)
    },
  })
}
