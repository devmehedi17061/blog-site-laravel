import { api } from '../api.js'
import { diffText } from './diff.js'

/**
 * The History panel shared by the post and page editors: the saved
 * versions, one compared with the current record, and restoring it.
 *
 * The editor supplies `draft.id` and `afterRevisionRestore()`, which reloads
 * what it shows once the server has put the old version back.
 */
export function revisionHistory(endpoint) {
  return {
    history: {
      open: false,
      loading: false,
      error: '',
      rows: [],
      limit: 50,
      canRestore: false,
      selected: null,
      compare: null,
      comparing: false,
      restoring: false,
      showUnchanged: false,
    },

    async openHistory() {
      if (!this.draft.id) return

      Object.assign(this.history, { open: true, loading: true, error: '', rows: [], selected: null, compare: null, showUnchanged: false })

      try {
        const payload = await api.get(`/${endpoint}/${this.draft.id}/revisions`)
        this.history.rows = payload.data
        this.history.limit = payload.limit
        this.history.canRestore = payload.can_restore

        // Open on the version before the current one: the usual question
        // is "what did I just change?".
        const first = payload.data.find((row) => !row.is_current) ?? payload.data[0]
        if (first) await this.selectRevision(first)
      } catch (err) {
        this.history.error = err.message
      } finally {
        this.history.loading = false
      }
    },

    closeHistory() {
      if (this.history.restoring) return
      this.history.open = false
    },

    async selectRevision(row) {
      this.history.selected = row
      this.history.comparing = true
      this.history.error = ''

      try {
        const payload = await api.get(`/revisions/${row.id}`)
        const current = Object.fromEntries(payload.current.map((field) => [field.key, field.value]))

        const fields = payload.fields.map((field) => {
          const now = current[field.key] ?? ''
          const changed = field.value !== now
          return {
            ...field,
            now,
            changed,
            // Short values read better side by side than as a diff.
            short: !field.value.includes('\n') && !now.includes('\n') && field.value.length + now.length < 160,
            lines: changed ? diffText(field.value, now) : [],
          }
        })

        // Ignore a late answer for a version no longer selected.
        if (this.history.selected?.id !== row.id) return
        this.history.compare = { fields, missingSections: payload.missing_sections }
      } catch (err) {
        this.history.error = err.message
      } finally {
        if (this.history.selected?.id === row.id) this.history.comparing = false
      }
    },

    get changedFields() {
      return this.history.compare?.fields.filter((field) => field.changed) ?? []
    },

    get unchangedFields() {
      return this.history.compare?.fields.filter((field) => !field.changed) ?? []
    },

    versionTitle(row) {
      if (!row) return ''
      if (row.label) return row.label
      if (row.is_first) return 'Created'
      return row.changes.length ? `Changed ${row.changes.slice(0, 3).join(', ').toLowerCase()}${row.changes.length > 3 ? ` +${row.changes.length - 3}` : ''}` : 'Saved'
    },

    async restoreRevision() {
      const row = this.history.selected
      if (!row || this.history.restoring) return

      const missing = this.history.compare?.missingSections ?? 0
      const ok = await this.$store.dialog.confirm({
        title: 'Restore this version',
        message: `Put back the version from ${row.when}? Its content replaces what is there now — unsaved edits in the editor are lost. `
          + 'The status, address and author stay as they are, and the current content stays in the history, so this can be undone.'
          + (missing ? ` ${missing} section(s) used a library section that has since been deleted and cannot come back.` : ''),
        confirmLabel: 'Restore version',
      })
      if (!ok) return

      this.history.restoring = true
      try {
        const payload = await api.post(`/revisions/${row.id}/restore`)
        await this.afterRevisionRestore()
        this.history.restoring = false
        this.history.open = false
        this.$store.ui.notify(payload.message, missing ? 'warn' : 'accent')
      } catch (err) {
        this.history.error = err.message
        this.history.restoring = false
      }
    },
  }
}
