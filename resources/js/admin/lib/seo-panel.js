import { api } from '../api.js'
import { debounce } from './debounce.js'

/**
 * The SEO panel, shared by the post and page editors. Mixed in with
 * extend(), so the getters stay getters.
 *
 * The slug is asked of the server rather than guessed here: Laravel turns
 * "শিশু বিশেষজ্ঞ ডাক্তার" into "sisu-biseshjng-daktar", and a browser-side
 * guess that only keeps a–z would show an empty address and fail every
 * "keyword in the slug" check for anything not written in English.
 */
export function seoPanel() {
  return {
    seoOpen: true,

    // What the server will make of the slug and of the focus keyword.
    serverSlug: '',
    keywordSlug: '',

    initSeo() {
      const refreshSlug = debounce(() => this.fetchSlug('serverSlug', this.draft.slug || this.draft.title), 350)
      const refreshKeyword = debounce(() => this.fetchSlug('keywordSlug', this.draft.focus_keyword), 350)

      this.$watch('draft.title', refreshSlug)
      this.$watch('draft.slug', refreshSlug)
      this.$watch('draft.focus_keyword', refreshKeyword)

      this.fetchSlug('serverSlug', this.draft.slug || this.draft.title)
      this.fetchSlug('keywordSlug', this.draft.focus_keyword)
    },

    async fetchSlug(target, text) {
      const value = String(text || '').trim()
      if (!value) {
        this[target] = ''
        return
      }

      try {
        const result = await api.get(`/slug?text=${encodeURIComponent(value)}`)
        this[target] = result.slug
      } catch {
        /* the checks fall back to their own guess */
      }
    },

    /** The draft as the SEO checks should see it. */
    get seoDraft() {
      return { ...this.draft, _slug: this.serverSlug, _keywordSlug: this.keywordSlug }
    },

    // ── social ─────────────────────────────────────────────────────────
    /**
     * A library image is kept as its path (/storage/…), not the full URL the
     * picker hands back, so it keeps working if the site moves domain.
     */
    async chooseSocialImage() {
      const media = await this.$store.picker.pick({ kind: 'image', title: 'Choose the social image' })
      if (!media) return

      try {
        const url = new URL(media.url, window.location.origin)
        this.draft.og_image = url.pathname.startsWith('/storage/') ? url.pathname : media.url
      } catch {
        this.draft.og_image = media.url
      }
    },

    get socialTitle() {
      return (this.draft.og_title || this.draft.meta_title || this.draft.title || 'Untitled').trim()
    },

    get socialDescription() {
      return (this.draft.og_description || this.draft.meta_description || this.draft.excerpt || '').trim()
    },

    /** What will be shared: the social image, else the featured image. */
    get socialImage() {
      return this.draft.og_image || this.draft.featured_image?.url || ''
    },

    get socialDomain() {
      return window.location.host.toUpperCase()
    },
  }
}
