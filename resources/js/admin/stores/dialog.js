/**
 * The panel's own confirm / prompt / alert, in place of the browser's.
 *
 *   if (!(await Alpine.store('dialog').confirm({ title: 'Delete form', message: '…', danger: true }))) return
 *   const url = await Alpine.store('dialog').prompt({ title: 'Add a link', label: 'Address', value: '' })
 *
 * The native dialogs cannot be styled, print the site's host name as their
 * title, and block the page. These resolve a promise instead: confirm() with
 * true or false, prompt() with the text or null when cancelled.
 *
 * The modal itself is rendered once, by partials/layout/dialog.blade.php.
 */
export default {
  open: false,
  kind: 'confirm',
  title: '',
  message: '',
  label: '',
  value: '',
  placeholder: '',
  confirmLabel: 'OK',
  cancelLabel: 'Cancel',
  danger: false,
  _resolve: null,

  confirm({ title = 'Are you sure?', message = '', confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false } = {}) {
    return this._ask({ kind: 'confirm', title, message, confirmLabel, cancelLabel, danger })
  },

  prompt({ title = 'Enter a value', message = '', label = '', value = '', placeholder = '', confirmLabel = 'Save', cancelLabel = 'Cancel' } = {}) {
    return this._ask({ kind: 'prompt', title, message, label, value: value ?? '', placeholder, confirmLabel, cancelLabel, danger: false })
  },

  alert({ title = 'Notice', message = '', confirmLabel = 'OK' } = {}) {
    return this._ask({ kind: 'alert', title, message, confirmLabel, cancelLabel: '', danger: false })
  },

  accept() {
    this._settle(this.kind === 'prompt' ? this.value : true)
  },

  cancel() {
    this._settle(this.kind === 'prompt' ? null : false)
  },

  _ask(options) {
    // A second question while one is open answers the first with "no",
    // rather than leaving its promise pending for ever.
    this.cancel()

    Object.assign(this, { label: '', value: '', placeholder: '' }, options)
    this.open = true

    return new Promise((resolve) => {
      this._resolve = resolve
    })
  },

  _settle(answer) {
    const resolve = this._resolve
    this._resolve = null
    this.open = false
    resolve?.(answer)
  },
}
