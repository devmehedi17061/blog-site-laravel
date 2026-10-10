import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { mediaBrowser } from '../lib/media-browser.js'

export default function media() {
  const browser = mediaBrowser({ perPage: 24 })

  // extend(), not spread — spread would flatten the browser's getters.
  return extend(browser, {
    dragging: false,
    selected: [],
    details: null,
    detailsSaving: false,
    detailsErrors: {},
    copied: null,
    renamingFolder: null,
    folderDraft: '',
    folderError: '',
    confirming: null,

    // ── selection ──────────────────────────────────────────────────────
    toggle(id) {
      this.selected = this.selected.includes(id)
        ? this.selected.filter((x) => x !== id)
        : [...this.selected, id]
    },

    isSelected(id) {
      return this.selected.includes(id)
    },

    selectAll() {
      this.selected = this.files.map((f) => f.id)
    },

    clearSelection() {
      this.selected = []
    },

    get allSelected() {
      return this.files.length > 0 && this.selected.length === this.files.length
    },

    // ── details panel ──────────────────────────────────────────────────
    async openDetails(file) {
      this.details = { ...file, usage: [] }
      this.detailsErrors = {}

      try {
        const payload = await api.get(`/media/${file.id}?with_usage=1`)
        this.details = { ...payload.data }
      } catch (err) {
        this.error = err.message
      }
    },

    closeDetails() {
      this.details = null
      this.detailsErrors = {}
    },

    detailsError(field) {
      return this.detailsErrors?.[field]?.[0] || ''
    },

    async saveDetails() {
      if (!this.details || this.detailsSaving) return

      this.detailsSaving = true
      this.detailsErrors = {}

      try {
        await api.put(`/media/${this.details.id}`, {
          name: this.details.name,
          alt_text: this.details.alt_text,
          caption: this.details.caption,
          folder_id: this.details.folder_id || null,
        })

        this.$store.ui.notify('File updated')
        await Promise.all([this.load(), this.loadFolders()])
      } catch (err) {
        this.detailsErrors = err.errors || {}
        if (!Object.keys(this.detailsErrors).length) this.error = err.message
      } finally {
        this.detailsSaving = false
      }
    },

    // ── copy link ──────────────────────────────────────────────────────
    async copyLink(file) {
      const url = new URL(file.url, window.location.origin).href

      try {
        await navigator.clipboard.writeText(url)
      } catch {
        // Clipboard access needs a secure context; fall back to a temp field.
        const field = document.createElement('textarea')
        field.value = url
        field.style.position = 'fixed'
        field.style.opacity = '0'
        document.body.appendChild(field)
        field.select()
        document.execCommand('copy')
        field.remove()
      }

      this.copied = file.id
      this.$store.ui.notify('Link copied')
      clearTimeout(this._copyTimer)
      this._copyTimer = setTimeout(() => (this.copied = null), 1800)
    },

    // ── folders ────────────────────────────────────────────────────────
    startNewFolder() {
      this.renamingFolder = 'new'
      this.folderDraft = ''
      this.folderError = ''
    },

    startRename(folder) {
      this.renamingFolder = folder.id
      this.folderDraft = folder.name
      this.folderError = ''
    },

    cancelFolder() {
      this.renamingFolder = null
      this.folderDraft = ''
      this.folderError = ''
    },

    async saveFolder() {
      const name = this.folderDraft.trim()
      if (!name) return this.cancelFolder()

      this.folderError = ''

      try {
        if (this.renamingFolder === 'new') {
          await api.post('/media/folders', { name })
          this.$store.ui.notify('Folder created')
        } else {
          await api.put(`/media/folders/${this.renamingFolder}`, { name })
          this.$store.ui.notify('Folder renamed')
        }

        this.cancelFolder()
        await this.loadFolders()
      } catch (err) {
        this.folderError = err.errors?.name?.[0] ?? err.message
      }
    },

    async deleteFolder(folder) {
      const confirmed = await this.$store.dialog.confirm({
        title: 'Delete folder',
        message: `Delete “${folder.name}”? Its ${folder.count} file(s) move to Unfiled — no file is deleted.`,
        confirmLabel: 'Delete folder',
        danger: true,
      })
      if (!confirmed) return

      try {
        await api.delete(`/media/folders/${folder.id}`)
        this.$store.ui.notify('Folder deleted')

        if (this.isFolder(folder.id)) this.query.folder = 'all'
        await Promise.all([this.loadFolders(), this.load()])
      } catch (err) {
        this.error = err.message
      }
    },

    // ── bulk actions ───────────────────────────────────────────────────
    async moveSelected(folderId) {
      if (this.selected.length === 0) return

      try {
        const payload = await api.post('/media/bulk', {
          action: 'move',
          ids: this.selected,
          folder_id: folderId || null,
        })

        this.$store.ui.notify(payload.message)
        this.clearSelection()
        await Promise.all([this.load(), this.loadFolders()])
      } catch (err) {
        this.error = err.message
      }
    },

    askDeleteSelected() {
      if (this.selected.length === 0) return
      this.confirming = this.files.filter((f) => this.selected.includes(f.id))
    },

    askDelete(file) {
      this.confirming = [file]
    },

    cancelDelete() {
      this.confirming = null
    },

    async confirmDelete() {
      const ids = (this.confirming ?? []).map((f) => f.id)
      this.confirming = null
      if (ids.length === 0) return

      try {
        const payload = await api.post('/media/bulk', { action: 'delete', ids })
        this.$store.ui.notify(payload.message)

        this.selected = this.selected.filter((id) => !ids.includes(id))
        if (ids.includes(this.details?.id)) this.closeDetails()

        await Promise.all([this.load(), this.loadFolders()])
      } catch (err) {
        this.error = err.message
      }
    },

    // ── misc ───────────────────────────────────────────────────────────
    async pickFiles(event) {
      await this.uploadFiles(event.target.files)
      event.target.value = ''
    },

    iconFor(kind) {
      return {
        pdf: 'PDF', doc: 'DOC', sheet: 'XLS', archive: 'ZIP',
        video: 'VID', audio: 'AUD', file: 'FILE',
      }[kind] ?? 'FILE'
    },
  })
}
