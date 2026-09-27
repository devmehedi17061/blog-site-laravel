import { extend } from '../lib/extend.js'
import { mediaBrowser } from '../lib/mediaBrowser.js'

/** The library, trimmed to a modal whose only job is to return one file. */
export default function mediaPicker() {
  const browser = mediaBrowser({ perPage: 18 })

  return extend(browser, {
    dragging: false,
    booted: false,

    /** Loads on first open, then re-reads so new uploads appear. */
    async openIfNeeded() {
      this.query.kind = this.$store.picker.kind

      if (!this.booted) {
        this.booted = true
        return this.init()
      }

      return this.load()
    },

    choose(file) {
      this.$store.picker.choose(file)
    },

    cancel() {
      this.$store.picker.cancel()
    },

    async pickFiles(event) {
      const uploaded = await this.uploadFiles(event.target.files)
      event.target.value = ''

      // Uploading from the picker almost always means "use this one".
      if (uploaded?.length === 1) this.choose(uploaded[0])
    },

    async onDropFiles(event) {
      this.dragging = false
      const uploaded = await this.uploadFiles(event.dataTransfer?.files)
      if (uploaded?.length === 1) this.choose(uploaded[0])
    },

    iconFor(kind) {
      return {
        pdf: 'PDF', doc: 'DOC', sheet: 'XLS', archive: 'ZIP',
        video: 'VID', audio: 'AUD', file: 'FILE',
      }[kind] ?? 'FILE'
    },
  })
}
