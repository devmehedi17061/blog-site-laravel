/**
 * A tiny broker so any screen can open the media picker and await a choice:
 *
 *   const file = await Alpine.store('picker').pick({ kind: 'image' })
 *
 * The modal itself lives in one place in the panel; this store just carries
 * the request and hands back the answer.
 */
export default {
  open: false,
  kind: 'all',
  title: 'Choose a file',
  _resolve: null,

  pick({ kind = 'all', title = 'Choose a file' } = {}) {
    // A second request while one is open cancels the first rather than
    // leaving its promise pending for ever.
    this._settle(null)

    this.kind = kind
    this.title = title
    this.open = true

    return new Promise((resolve) => {
      this._resolve = resolve
    })
  },

  choose(file) {
    this._settle(file)
  },

  cancel() {
    this._settle(null)
  },

  _settle(value) {
    const resolve = this._resolve
    this._resolve = null
    this.open = false
    resolve?.(value)
  },
}
