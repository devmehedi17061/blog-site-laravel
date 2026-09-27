import { api } from '../api.js'
import { extend } from '../lib/extend.js'
import { pageSections } from '../lib/sections/pageSections.js'
import { analyse, effectiveTitle, effectiveDescription, effectiveSlug, snippet } from '../lib/seo.js'
import { seoPanel } from '../lib/seoPanel.js'
import { revisionHistory } from '../lib/revisions.js'

function blankPage() {
  return {
    id: null,
    title: '',
    slug: '',
    status: 'Draft',
    template: 'blank',
    layout: 'default',
    excerpt: '',
    user_id: '',
    media_id: null,
    featured_image: null,
    meta_title: '',
    meta_description: '',
    focus_keyword: '',
    canonical_url: '',
    robots: 'index, follow',
    og_title: '',
    og_description: '',
    og_image: '',
    custom_css: '',
    custom_js: '',
    published_at: '',
    public_url: '',
    preview_url: '',
    path: '',
    is_home: false,
    is_live: false,
    sections_count: 0,
  }
}

export default function pageEditor() {
  return extend(extend(extend(pageSections(), seoPanel()), revisionHistory('pages')), {
    draft: blankPage(),
    options: { statuses: [], templates: [], authors: [], layouts: [] },
    homeSaving: false,

    loading: true,
    saving: false,
    uploading: false,
    error: '',
    errors: {},
    tab: 'content',

    async init() {
      const param = this.$store.ui.param ?? ''

      // Moving from one page's editor straight to another's — Back and
      // Forward, a pasted link — keeps this component mounted, so it must
      // load the new page itself or it would go on editing the old one.
      this.$watch('$store.ui.param', (next) => {
        if (!next || next.startsWith('new') || Number(next) === this.draft.id) return
        this.switchTo(next)
      })

      try {
        const options = await api.get('/pages/options')
        this.options = options

        // `new:landing` carries the template chosen in the list.
        if (param.startsWith('new')) {
          this.draft.template = param.split(':')[1] || 'blank'
          this.applyTemplateLayout()
        } else if (param) {
          await this.fetchPage(param)
        }
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }

      this.initSeo()
    },

    async switchTo(id) {
      this.loading = true
      this.error = ''
      this.errors = {}
      this.tab = 'content'

      try {
        await this.fetchPage(id)
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    },

    /** After History → Restore: show what the server now holds, sections too. */
    async afterRevisionRestore() {
      await this.fetchPage(this.draft.id)
    },

    async fetchPage(id) {
      const payload = await api.get(`/pages/${id}`)
      this.draft = { ...blankPage(), ...payload.data }
      this.adoptSections(payload.data.sections ?? [])
    },

    get isNew() {
      return !this.draft.id
    },

    /** A new page starts with the layout its template suggests. */
    applyTemplateLayout() {
      const template = this.options.templates?.find((t) => t.value === this.draft.template)
      this.draft.layout = template?.layout ?? 'default'
    },

    get layoutSummary() {
      return this.options.layouts?.find((l) => l.value === this.draft.layout)?.summary ?? ''
    },

    // ── homepage ───────────────────────────────────────────────────────
    /** Why this page cannot be made the homepage yet, or '' when it can. */
    get homeBlocker() {
      if (this.isNew) return 'Save the page first.'
      if (!this.draft.is_live) return 'Publish the page first — only a live page can be the homepage.'
      return ''
    },

    async makeHome() {
      if (this.homeBlocker || this.homeSaving) return

      const ok = await this.$store.dialog.confirm({
        title: 'Set as homepage',
        message: `Visitors to ${window.location.host}/ will see "${this.draft.title}". Its own address, ${this.draft.slug}, will redirect to /.`,
        confirmLabel: 'Set as homepage',
      })
      if (!ok) return

      this.homeSaving = true
      try {
        const result = await api.put('/pages/homepage', { page_id: this.draft.id })
        this.draft.is_home = true
        this.draft.path = '/'
        this.draft.public_url = `${window.location.origin}/`
        this.$store.ui.notify(result.message)
      } catch (err) {
        this.error = err.message
      } finally {
        this.homeSaving = false
      }
    },

    /** Statuses that would take the homepage off the site are not offered. */
    statusDisabled(status) {
      return this.draft.is_home && status !== 'Published'
    },

    get templateSummary() {
      return this.options.templates?.find((t) => t.value === this.draft.template)?.summary ?? ''
    },

    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    // ── SEO ────────────────────────────────────────────────────────────
    get seo() {
      // Pages are built from sections, so the SEO reader looks at the module
      // text rather than a body field the page does not have.
      return analyse({ ...this.seoDraft, body: this.sectionsAsHtml })
    },

    get previewTitle() {
      return effectiveTitle(this.draft) || 'Untitled page'
    },

    get previewDescription() {
      return snippet(effectiveDescription(this.draft)) || 'No description yet — Google will pick its own text.'
    },

    get previewUrl() {
      return `${window.location.host}${this.storedSlug}`
    },

    /** Pages keep a leading slash in the database, so show it here too. */
    get storedSlug() {
      // The server's answer when it has one: it transliterates, a guess cannot.
      if (this.serverSlug) return `/${this.serverSlug}`

      const typed = (this.draft.slug ?? '').trim().replace(/^\/+|\/+$/g, '')
      if (typed) return `/${typed}`

      const derived = effectiveSlug({ ...this.draft, slug: '' })
      return derived ? `/${derived}` : '/page-slug'
    },

    get slugPreview() {
      return this.storedSlug === '/page-slug' ? 'built from the title' : this.storedSlug
    },

    // ── featured image ─────────────────────────────────────────────────
    async chooseFeaturedImage() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose a featured image' })
      if (!media) return

      this.draft.media_id = media.id
      this.draft.featured_image = media
    },

    clearFeaturedImage() {
      this.draft.media_id = null
      this.draft.featured_image = null
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save(status = null) {
      if (this.saving) return

      this.saving = true
      this.errors = {}
      this.error = ''

      if (status) this.draft.status = status

      const payload = { ...this.draft }
      for (const key of ['featured_image', 'public_url', 'preview_url', 'path', 'is_home', 'is_live', 'can', 'updated', 'author', 'sections', 'sections_count']) {
        delete payload[key]
      }

      try {
        const wasNew = this.isNew
        const result = wasNew
          ? await api.post('/pages', payload)
          : await api.put(`/pages/${this.draft.id}`, payload)

        const saved = result.data
        this.draft = { ...this.draft, ...saved }

        // A brand new page arrives with its template's sections already built.
        if (wasNew) {
          this.adoptSections(saved.sections ?? [])
          this.$store.ui.go('pages', saved.id)
        } else if (this.dirty) {
          await this.saveSections(this.draft.id)
        }

        this.$store.ui.notify(wasNew ? 'Page created' : 'Page saved')
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(this.errors).length ? 'Some fields need attention.' : err.message
        if (Object.keys(this.errors).length) this.tab = this.tabForErrors()
      } finally {
        this.saving = false
      }
    },

    tabForErrors() {
      const seoFields = ['meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image']
      if (Object.keys(this.errors).some((f) => f.startsWith('custom_'))) return 'code'
      return Object.keys(this.errors).some((f) => seoFields.includes(f)) ? 'seo' : 'content'
    },

    saveSectionsNow() {
      return this.saveSections(this.draft.id)
    },

    backToList() {
      this.$store.ui.go('pages')
    },
  })
}
