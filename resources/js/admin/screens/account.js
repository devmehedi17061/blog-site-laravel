import QRCode from 'qrcode'
import { api } from '../api.js'

/**
 * My account: name and email, password, two-factor sign-in and sessions,
 * for whoever is signed in.
 */
export default function account() {
  return {
    loading: true,
    error: '',
    data: null,

    profile: { name: '', email: '', current_password: '' },
    profileErrors: {},
    profileSaving: false,

    password: { current_password: '', password: '', password_confirmation: '' },
    passwordErrors: {},
    passwordSaving: false,
    showPasswords: false,

    // Two-factor setup, step by step: choose → scan or receive → confirm → codes.
    setup: null, // { method, secret, qr, code, error, busy, message }
    recoveryCodes: null,
    dangerPassword: '',
    dangerError: '',

    async init() {
      try {
        this.adopt(await api.get('/account'))
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    adopt(payload) {
      this.data = payload
      this.profile = { name: payload.account.name, email: payload.account.email, current_password: '' }

      // Done what was owed: open the rest of the panel again.
      const owes = this.$store.ui.owes
      if ((owes === 'two_factor_required' && payload.two_factor.enabled) || (owes === 'password_expired' && !payload.account.password_expired)) {
        this.$store.ui.settle()
      }
    },

    get owes() {
      return this.$store.ui.owes
    },

    get emailChanged() {
      return this.data && this.profile.email.trim() !== this.data.account.email
    },

    // ── profile ────────────────────────────────────────────────────────
    async saveProfile() {
      this.profileSaving = true
      this.profileErrors = {}

      try {
        const result = await api.put('/account/profile', this.profile)
        this.adopt(result)
        this.$store.auth.user = { ...this.$store.auth.user, name: result.account.name, email: result.account.email }
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.profileErrors = err.errors || {}
        if (!Object.keys(this.profileErrors).length) this.error = err.message
      } finally {
        this.profileSaving = false
      }
    },

    // ── password ───────────────────────────────────────────────────────
    async savePassword() {
      this.passwordSaving = true
      this.passwordErrors = {}

      try {
        const result = await api.put('/account/password', this.password)
        this.password = { current_password: '', password: '', password_confirmation: '' }
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.passwordErrors = err.errors || {}
        if (!Object.keys(this.passwordErrors).length) this.error = err.message
      } finally {
        this.passwordSaving = false
      }
    },

    // ── two-factor ─────────────────────────────────────────────────────
    async startSetup(method) {
      this.recoveryCodes = null
      this.setup = { method, secret: '', qr: '', code: '', error: '', busy: true, message: '' }

      try {
        if (method === 'app') {
          const result = await api.post('/account/two-factor/app')
          this.setup.secret = result.secret
          // Drawn here, from the otpauth:// address; nothing leaves the page.
          this.setup.qr = await QRCode.toDataURL(result.uri, { margin: 1, width: 200 })
        } else {
          const result = await api.post('/account/two-factor/email')
          this.setup.message = result.message
        }
      } catch (err) {
        this.setup.error = err.message
      } finally {
        this.setup.busy = false
      }
    },

    cancelSetup() {
      this.setup = null
    },

    async confirmSetup() {
      if (!this.setup || this.setup.busy) return
      this.setup.busy = true
      this.setup.error = ''

      try {
        const result = await api.post('/account/two-factor/confirm', { code: this.setup.code })
        this.recoveryCodes = result.recovery_codes
        this.setup = null
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.setup.error = err.errors?.code?.[0] || err.message
        this.setup.busy = false
      }
    },

    async newRecoveryCodes() {
      this.dangerError = ''
      try {
        const result = await api.post('/account/two-factor/recovery-codes', { current_password: this.dangerPassword })
        this.recoveryCodes = result.recovery_codes
        this.dangerPassword = ''
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.dangerError = err.errors?.current_password?.[0] || err.message
      }
    },

    async turnOff() {
      this.dangerError = ''
      const ok = await this.$store.dialog.confirm({
        title: 'Turn off two-factor',
        message: 'Signing in will only need your password again. Your recovery codes stop working.',
        confirmLabel: 'Turn off',
        danger: true,
      })
      if (!ok) return

      try {
        const result = await api.delete('/account/two-factor', { current_password: this.dangerPassword })
        this.dangerPassword = ''
        this.recoveryCodes = null
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.dangerError = err.errors?.current_password?.[0] || err.message
      }
    },

    copyCodes() {
      navigator.clipboard?.writeText(this.recoveryCodes.join('\n'))
      this.$store.ui.notify('Recovery codes copied')
    },

    downloadCodes() {
      const text = `Recovery codes for ${this.data.account.email} at ${window.location.host}\nEach works once.\n\n${this.recoveryCodes.join('\n')}\n`
      const link = document.createElement('a')
      link.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }))
      link.download = 'recovery-codes.txt'
      link.click()
      URL.revokeObjectURL(link.href)
    },

    // ── sessions ───────────────────────────────────────────────────────
    async endSession(session) {
      try {
        const result = await api.delete(`/account/sessions/${session.key}`)
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },

    async endOthers() {
      const ok = await this.$store.dialog.confirm({
        title: 'Sign out other devices',
        message: 'Every other browser signed in to your account is signed out now. This one stays signed in.',
        confirmLabel: 'Sign them out',
      })
      if (!ok) return

      try {
        const result = await api.post('/account/sessions/sign-out-others')
        this.adopt(result)
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      }
    },
  }
}
