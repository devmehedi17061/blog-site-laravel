import { api } from '../api.js'

/**
 * Settings → Updates: the core version this site runs, the newest release on
 * GitHub, installing it (with a backup first) and going back.
 */
export default function updates() {
  return {
    loading: true,
    busy: '',
    error: '',
    data: null,
    log: [],
    form: { auto: 'none', token: '', token_clear: false },

    get can() {
      return this.$store.auth.user?.role?.slug === 'super-admin'
    },

    async init() {
      try {
        this.adopt(await api.get('/updates'))
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    adopt(payload) {
      this.data = payload
      this.form.auto = payload.auto
      this.form.token = ''
      this.form.token_clear = false
      if (payload.log) this.log = payload.log
      else if (!this.log.length) this.log = payload.last_log ?? []
    },

    async run(kind, path, method = 'post', body = null) {
      this.busy = kind
      this.error = ''
      try {
        const payload = await api[method](path, body ?? {})
        this.adopt(payload)
        if (payload.message) this.$store.ui.notify(payload.message)
      } catch (err) {
        this.error = err.message
        if (err.errors?.log) this.log = err.errors.log
        this.$store.ui.notify(err.message, 'warn')
        // The status still came back with the error: show it.
        try { this.adopt(await api.get('/updates')) } catch {}
      } finally {
        this.busy = ''
      }
    },

    check() {
      return this.run('check', '/updates/check')
    },

    async install() {
      const next = this.data?.latest
      const ok = await this.$store.dialog.confirm({
        title: `Update to ${next?.version}?`,
        message: 'The database and the current code are backed up first, then the new version is copied in and the database is migrated. Pages, posts, media, settings and this site\'s pack are not touched. It takes a minute or two — keep this tab open.',
        confirmLabel: 'Update now',
      })
      if (ok) await this.run('install', '/updates/install')
    },

    async rollback() {
      const ok = await this.$store.dialog.confirm({
        title: 'Go back to the previous version?',
        message: 'The code saved before the last update is put back. The database is left as it is (a backup of it is in the list below).',
        confirmLabel: 'Go back',
        danger: true,
      })
      if (ok) await this.run('rollback', '/updates/rollback')
    },

    save() {
      return this.run('save', '/updates/settings', 'put', this.form)
    },

    size(bytes) {
      return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB'
    },

    when(iso) {
      return iso ? new Date(iso).toLocaleString() : 'never'
    },
  }
}
