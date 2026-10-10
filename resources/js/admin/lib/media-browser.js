import { api } from '../api.js'
import { debounce } from './debounce.js'
import { uploadWithProgress } from './upload.js'

/**
 * Browsing, filtering and uploading — everything the full Media Library screen
 * and the picker modal both need. Each of them adds its own behaviour on top.
 */
export function mediaBrowser({ kind = 'all', perPage = 24 } = {}) {
  return {
    files: [],
    folders: [],
    folderMeta: { total: 0, unfiled: 0 },
    meta: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },

    loading: true,
    error: '',
    uploading: false,
    uploads: [],

    query: {
      search: '',
      folder: 'all',
      kind,
      sort: 'created_at',
      direction: 'desc',
      per_page: perPage,
      page: 1,
    },

    async init() {
      this.searchDebounced = debounce(() => this.applyFilters(), 350)
      await Promise.all([this.loadFolders(), this.load()])
    },

    async load() {
      this.loading = true
      this.error = ''

      try {
        const payload = await api.get(`/media?${new URLSearchParams(this.query)}`)
        this.files = payload.data
        this.meta = payload.meta

        // Deleting the last file on the last page leaves us past the end.
        if (this.meta.current_page > this.meta.last_page && this.meta.last_page >= 1) {
          this.query.page = this.meta.last_page
          return this.load()
        }
      } catch (err) {
        this.error = err.message
        this.files = []
      } finally {
        this.loading = false
      }
    },

    async loadFolders() {
      try {
        const payload = await api.get('/media/folders')
        this.folders = payload.data
        this.folderMeta = payload.meta ?? { total: 0, unfiled: 0 }
      } catch {
        /* the library still works with only All files and Unfiled */
      }
    },

    // ── filters ────────────────────────────────────────────────────────
    applyFilters() {
      this.query.page = 1
      this.load()
    },

    setFolder(folder) {
      this.query.folder = String(folder)
      this.applyFilters()
    },

    setKind(value) {
      this.query.kind = value
      this.applyFilters()
    },

    isFolder(folder) {
      return this.query.folder === String(folder)
    },

    goToPage(page) {
      if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) return
      this.query.page = page
      this.load()
    },

    get pageNumbers() {
      const total = this.meta.last_page || 1
      const current = this.meta.current_page || 1
      const start = Math.max(1, Math.min(current - 2, total - 4))
      const end = Math.min(total, start + 4)

      return Array.from({ length: end - start + 1 }, (_, i) => start + i)
    },

    get resultCount() {
      const { from, to, total } = this.meta
      if (!total) return 'No files'
      return `Showing ${from}–${to} of ${total}`
    },

    get isEmpty() {
      return !this.loading && this.files.length === 0
    },

    get folderName() {
      if (this.query.folder === 'all') return 'All files'
      if (this.query.folder === 'unfiled') return 'Unfiled'
      return this.folders.find((f) => String(f.id) === this.query.folder)?.name ?? 'All files'
    },

    // ── uploading ──────────────────────────────────────────────────────
    async uploadFiles(fileList) {
      const files = Array.from(fileList || [])
      if (files.length === 0) return []

      this.uploading = true
      this.error = ''
      this.uploads = files.map((file) => ({ name: file.name, percent: 0, status: 'uploading' }))

      const form = new FormData()
      files.forEach((file) => form.append('files[]', file))

      // Uploading into the folder being viewed is what people expect.
      if (!['all', 'unfiled'].includes(this.query.folder)) {
        form.append('folder_id', this.query.folder)
      }

      try {
        const payload = await uploadWithProgress('/media', form, (percent) => {
          this.uploads = this.uploads.map((u) => ({ ...u, percent }))
        })

        this.uploads = this.uploads.map((u) => ({ ...u, percent: 100, status: 'done' }))
        await Promise.all([this.load(), this.loadFolders()])
        setTimeout(() => (this.uploads = []), 1500)

        return payload.data
      } catch (err) {
        // Laravel reports per-file failures as files.0, files.1 …
        const perFile = Object.entries(err.errors || {})
          .filter(([key]) => key.startsWith('files.'))
          .map(([key, messages]) => [Number(key.split('.')[1]), messages[0]])

        this.uploads = this.uploads.map((u, i) => {
          const failure = perFile.find(([index]) => index === i)
          return failure ? { ...u, status: 'failed', message: failure[1] } : { ...u, status: 'done', percent: 100 }
        })

        this.error = perFile.length ? 'Some files were rejected.' : err.message
        return []
      } finally {
        this.uploading = false
      }
    },

    onDrop(event) {
      this.dragging = false
      this.uploadFiles(event.dataTransfer?.files)
    },
  }
}
