import { api } from '../api.js'
import { createEditor, TEXT_COLORS, HIGHLIGHTS } from '../lib/rich-editor.js'
import { IMAGE_SIZES, IMAGE_ALIGNS } from '../lib/post-image.js'
import { analyse, effectiveTitle, effectiveDescription, effectiveSlug, snippet } from '../lib/seo.js'
import { seoPanel } from '../lib/seo-panel.js'
import { extend } from '../lib/extend.js'
import { revisionHistory } from '../lib/revisions.js'

function blankPost() {
  return {
    id: null,
    title: '',
    slug: '',
    excerpt: '',
    body: '',
    content_spacing: 'normal',
    status: 'Draft',
    category_id: '',
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
  }
}

export default function postEditor() {
  /**
   * The TipTap instance lives in this closure, never as a property of the
   * Alpine component. Alpine wraps every property it hands back in a reactive
   * Proxy, and ProseMirror compares editor state by identity — a proxied
   * instance throws "Applying a mismatched transaction" on the first edit.
   */
  let editor = null

  // extend(), not spread — spread would flatten the SEO panel's getters.
  return extend(extend(seoPanel(), revisionHistory('posts')), {
    draft: blankPost(),
    options: { statuses: [], categories: [], authors: [] },

    loading: true,
    saving: false,
    uploading: false,
    error: '',
    errors: {},
    tab: 'content',

    // Bumped on every editor transaction so the toolbar's active states and
    // the SEO panel recompute — TipTap holds its own state outside Alpine.
    revision: 0,

    textColors: TEXT_COLORS,
    highlights: HIGHLIGHTS,
    imageSizes: IMAGE_SIZES,
    imageAligns: IMAGE_ALIGNS,

    spacings: [
      { value: 'tight', label: 'Tight', hint: 'No gap between paragraphs' },
      { value: 'normal', label: 'Normal', hint: 'The default' },
      { value: 'loose', label: 'Loose', hint: 'Extra breathing room' },
    ],

    async init() {
      const param = this.$store.ui.param

      // Back / Forward from one post's editor to another's keeps this
      // component mounted; load the other post rather than keep editing
      // (and saving over) the first.
      this.$watch('$store.ui.param', async (next) => {
        if (!next || next === 'new' || Number(next) === this.draft.id) return

        this.loading = true
        try {
          await this.fetchPost(next)
          editor?.commands.setContent(this.draft.body || '', { emitUpdate: false })
        } catch (err) {
          this.error = err.message
        } finally {
          this.loading = false
        }
      })

      try {
        const [options] = await Promise.all([
          api.get('/posts/options'),
          param && param !== 'new' ? this.fetchPost(param) : Promise.resolve(),
        ])
        this.options = options
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }

      this.initSeo()
      this.$nextTick(() => this.mountEditor())
    },

    /** After History → Restore: show what the server now holds. */
    async afterRevisionRestore() {
      await this.fetchPost(this.draft.id)
      editor?.commands.setContent(this.draft.body || '', { emitUpdate: false })
      this.revision++
    },

    async fetchPost(id) {
      const payload = await api.get(`/posts/${id}`)
      this.draft = { ...blankPost(), ...payload.data }
    },

    mountEditor() {
      const element = this.$refs.editor
      if (!element || editor) return

      editor = createEditor({
        element,
        content: this.draft.body || '',
        placeholder: 'Write the post…  Use the toolbar for headings, images and colour.',
        onUpdate: (html) => {
          this.draft.body = html
          this.revision++
        },
      })

      editor.on('selectionUpdate', () => this.revision++)
      editor.on('transaction', () => this.revision++)
    },

    destroy() {
      editor?.destroy()
      editor = null
    },

    /** Raw TipTap instance, for tests and anything needing the real object. */
    tiptap() {
      return editor
    },

    // ── toolbar ────────────────────────────────────────────────────────
    run(command, ...args) {
      if (!editor) return
      editor.chain().focus()[command](...args).run()
    },

    /**
     * One snapshot of everything the toolbar highlights, recomputed whenever
     * `revision` changes.
     *
     * Templates must read these as properties (`active.bold`), not call a
     * method. A method that reads `this.revision` is not reliably tracked as
     * a dependency inside an `x-for` scope, which left the image bar's
     * buttons stuck on their first render.
     */
    get active() {
      const tracked = this.revision // read so Alpine re-runs on every change
      if (!editor) return { heading: 0, image: null, tracked }

      const align = (value) => editor.isActive({ textAlign: value })

      return {
        tracked,
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        underline: editor.isActive('underline'),
        strike: editor.isActive('strike'),
        code: editor.isActive('code'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
        blockquote: editor.isActive('blockquote'),
        codeBlock: editor.isActive('codeBlock'),
        link: editor.isActive('link'),
        alignLeft: align('left'),
        alignCenter: align('center'),
        alignRight: align('right'),
        heading: [2, 3, 4].find((level) => editor.isActive('heading', { level })) ?? 0,
        image: editor.isActive('image') ? { ...editor.getAttributes('image') } : null,
      }
    },

    isActive(name, attrs = {}) {
      // Touching revision makes Alpine re-evaluate when the cursor moves.
      return this.revision >= 0 && !!editor?.isActive(name, attrs)
    },

    setHeading(level) {
      if (level === 0) this.run('setParagraph')
      else this.run('toggleHeading', { level })
    },

    get currentHeading() {
      return this.active.heading
    },

    setColor(value) {
      value ? this.run('setColor', value) : this.run('unsetColor')
    },

    setHighlight(value) {
      value ? this.run('toggleHighlight', { color: value }) : this.run('unsetHighlight')
    },

    async addLink() {
      if (!editor) return

      const previous = editor.getAttributes('link')?.href ?? ''
      const url = await this.$store.dialog.prompt({
        title: previous ? 'Edit link' : 'Add a link',
        label: 'Link address',
        message: previous ? 'Clear the address to remove the link.' : '',
        value: previous,
        placeholder: 'https://example.com or /about',
        confirmLabel: previous ? 'Update link' : 'Add link',
      })

      if (url === null) return
      if (url === '') return this.run('unsetLink')

      editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
    },

    // ── images ─────────────────────────────────────────────────────────
    /** Opens the media library and drops the chosen file into the body. */
    async insertImageFromLibrary() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose an image' })
      if (!media || !editor) return

      editor.chain().focus().setImage({
        src: media.url,
        alt: media.alt_text || media.name,
        title: media.alt_text || media.name,
        caption: media.caption || '',
      }).run()

      this.revision++
    },

    async chooseFeaturedImage() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose a featured image' })
      if (!media) return

      this.draft.media_id = media.id
      this.draft.featured_image = media
    },

    async insertImage(event) {
      const file = event.target.files?.[0]
      event.target.value = ''
      if (!file || !editor) return

      const media = await this.uploadFile(file)
      if (!media) return

      const alt = (await this.$store.dialog.prompt({
        title: 'Describe the image',
        label: 'Alt text',
        message: 'Read aloud by screen readers and used by search engines.',
        value: media.name,
        confirmLabel: 'Insert image',
        cancelLabel: 'Skip',
      })) || ''

      editor.chain().focus().setImage({ src: media.url, alt, title: alt }).run()
    },

    async setFeaturedImage(event) {
      const file = event.target.files?.[0]
      event.target.value = ''
      if (!file) return

      const media = await this.uploadFile(file)
      if (!media) return

      this.draft.media_id = media.id
      this.draft.featured_image = media
    },

    clearFeaturedImage() {
      this.draft.media_id = null
      this.draft.featured_image = null
    },

    async uploadFile(file) {
      this.uploading = true
      this.error = ''

      try {
        const form = new FormData()
        // The library endpoint takes a batch; the editor only ever sends one.
        form.append('files[]', file)
        const payload = await api.upload('/media', form)
        return payload.data?.[0] ?? null
      } catch (err) {
        this.error = err.errors?.['files.0']?.[0] ?? err.errors?.files?.[0] ?? err.message
        return null
      } finally {
        this.uploading = false
      }
    },

    /** Shift+Enter does this too; the button is for people who do not know that. */
    insertLineBreak() {
      this.run('setHardBreak')
    },

    setSpacing(value) {
      this.draft.content_spacing = value
    },

    get spacingLabel() {
      return this.spacings.find((s) => s.value === this.draft.content_spacing)?.label ?? 'Normal'
    },

    // ── selected image ─────────────────────────────────────────────────
    get imageSelected() {
      return this.active.image !== null
    },

    imageAttr(name) {
      if (!editor || this.revision < 0) return ''
      return editor.getAttributes('image')?.[name] ?? ''
    },

    setImageAttr(name, value) {
      if (!editor) return
      editor.chain().focus().updateAttributes('image', { [name]: value }).run()
      this.revision++
    },

    async editImageAlt() {
      const current = this.imageAttr('alt')
      const next = await this.$store.dialog.prompt({
        title: 'Alt text',
        label: 'Describe the image',
        message: 'Read aloud by screen readers and used by search engines.',
        value: current,
      })
      if (next !== null) this.setImageAttr('alt', next)
    },

    async editImageCaption() {
      const current = this.imageAttr('caption')
      const next = await this.$store.dialog.prompt({
        title: 'Caption',
        label: 'Shown under the image on the post',
        value: current,
      })
      if (next !== null) this.setImageAttr('caption', next)
    },

    removeImage() {
      if (!editor) return
      editor.chain().focus().deleteSelection().run()
      this.revision++
    },

    // ── SEO ────────────────────────────────────────────────────────────
    get seo() {
      return analyse(this.seoDraft)
    },

    get previewTitle() {
      return effectiveTitle(this.draft) || 'Untitled post'
    },

    get previewDescription() {
      return snippet(effectiveDescription(this.draft)) || 'No description yet — Google will pick its own text.'
    },

    get previewUrl() {
      return `${window.location.host} › blog › ${effectiveSlug(this.seoDraft) || 'post-slug'}`
    },

    /** Shown as the slug field's placeholder while it is still blank. */
    get slugPreview() {
      return effectiveSlug(this.seoDraft) || 'built from the title'
    },

    get isNew() {
      return !this.draft.id
    },

    fieldError(field) {
      return this.errors?.[field]?.[0] || ''
    },

    // ── saving ─────────────────────────────────────────────────────────
    async save(status = null) {
      if (this.saving) return

      this.saving = true
      this.errors = {}
      this.error = ''

      if (status) this.draft.status = status

      const payload = { ...this.draft }
      delete payload.featured_image
      delete payload.public_url
      delete payload.can
      delete payload.date
      delete payload.created_at
      delete payload.author
      delete payload.category

      try {
        const wasNew = this.isNew
        const result = wasNew
          ? await api.post('/posts', payload)
          : await api.put(`/posts/${this.draft.id}`, payload)

        const saved = result.data
        this.draft = { ...this.draft, ...saved }
        this.$store.ui.notify(wasNew ? 'Post created' : 'Post saved')

        // Move the URL onto the saved record so a refresh reopens it.
        if (wasNew) this.$store.ui.go('posts', saved.id)
      } catch (err) {
        this.errors = err.errors || {}
        this.error = Object.keys(err.errors || {}).length
          ? 'Some fields need attention.'
          : err.message
        if (Object.keys(this.errors).length) this.tab = this.tabForErrors()
      } finally {
        this.saving = false
      }
    },

    /** Jumps to the tab holding the first failed field. */
    tabForErrors() {
      const seoFields = ['meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image']
      if (Object.keys(this.errors).some((f) => f.startsWith('custom_'))) return 'code'
      return Object.keys(this.errors).some((f) => seoFields.includes(f)) ? 'seo' : 'content'
    },

    backToList() {
      this.$store.ui.go('posts')
    },
  })
}
