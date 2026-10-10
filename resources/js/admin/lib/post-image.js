import Image from '@tiptap/extension-image'
import { mergeAttributes } from '@tiptap/core'

export const IMAGE_SIZES = [
  { value: 'small', label: 'Small', hint: '25%' },
  { value: 'medium', label: 'Medium', hint: '50%' },
  { value: 'large', label: 'Large', hint: '75%' },
  { value: 'full', label: 'Full', hint: '100%' },
]

export const IMAGE_ALIGNS = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Centre' },
  { value: 'right', label: 'Right' },
]

/**
 * The stock Image node is a bare <img>. This one carries a size, an alignment
 * and a caption, and serialises to a <figure> so the caption is real markup
 * rather than a styled paragraph that could drift away from its image.
 *
 * Plain <img> tags from older posts still parse, falling back to full width.
 */
export const PostImage = Image.extend({
  name: 'image',

  addAttributes() {
    return {
      ...this.parent?.(),
      size: {
        default: 'full',
        parseHTML: (el) => el.getAttribute('data-size') || 'full',
        renderHTML: () => ({}), // carried on the figure, not the img
      },
      align: {
        default: 'center',
        parseHTML: (el) => el.getAttribute('data-align') || 'center',
        renderHTML: () => ({}),
      },
      caption: {
        default: '',
        parseHTML: (el) => el.getAttribute('data-caption') || '',
        renderHTML: () => ({}),
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'figure[data-post-image]',
        getAttrs: (figure) => {
          const img = figure.querySelector('img')
          if (!img) return false

          return {
            src: img.getAttribute('src'),
            alt: img.getAttribute('alt') || '',
            title: img.getAttribute('title'),
            size: figure.getAttribute('data-size') || 'full',
            align: figure.getAttribute('data-align') || 'center',
            caption: figure.querySelector('figcaption')?.textContent?.trim() || '',
          }
        },
      },
      // Images written before captions existed.
      { tag: 'img[src]' },
    ]
  },

  renderHTML({ HTMLAttributes, node }) {
    const { size, align, caption } = node.attrs
    const img = ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]

    const figure = [
      'figure',
      {
        'data-post-image': '',
        'data-size': size || 'full',
        'data-align': align || 'center',
        class: `post-figure is-${size || 'full'} align-${align || 'center'}`,
      },
      img,
    ]

    if (caption) figure.push(['figcaption', {}, caption])

    return figure
  },
})
