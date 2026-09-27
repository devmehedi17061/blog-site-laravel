import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Highlight from '@tiptap/extension-highlight'
import TextAlign from '@tiptap/extension-text-align'
import Placeholder from '@tiptap/extension-placeholder'
import { Color, TextStyle } from '@tiptap/extension-text-style'
import { PostImage } from './postImage.js'

/**
 * TipTap set-up for the post body.
 *
 * StarterKit already bundles link and underline in v3, so they are configured
 * through it rather than added again — adding them twice throws a duplicate
 * extension warning and breaks the toolbar's active states.
 */
export function createEditor({ element, content = '', placeholder = 'Start writing…', onUpdate }) {
  return new Editor({
    element,
    content,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3, 4] },
        link: {
          openOnClick: false,
          autolink: true,
          HTMLAttributes: { rel: 'noopener', target: '_blank' },
        },
      }),
      TextStyle,
      Color,
      Highlight.configure({ multicolor: true }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      PostImage.configure({ inline: false, HTMLAttributes: { loading: 'lazy' } }),
      Placeholder.configure({ placeholder }),
    ],
    editorProps: {
      attributes: { class: 'editor-surface rich-content' },
    },
    onUpdate: ({ editor }) => onUpdate?.(editor.getHTML(), editor.getText()),
  })
}

/** Text colours offered in the toolbar swatch. */
export const TEXT_COLORS = [
  { label: 'Default', value: null },
  { label: 'Ink', value: '#1c1a19' },
  { label: 'Grey', value: '#605d5d' },
  { label: 'Accent', value: '#ec3013' },
  { label: 'Deep red', value: '#7c1405' },
  { label: 'Green', value: '#1c6b3f' },
  { label: 'Blue', value: '#1d4ed8' },
  { label: 'Amber', value: '#8a5a00' },
]

/** Highlight (background) colours. */
export const HIGHLIGHTS = [
  { label: 'None', value: null },
  { label: 'Yellow', value: '#fff3bf' },
  { label: 'Pink', value: '#ffe0d9' },
  { label: 'Green', value: '#e9f7ef' },
  { label: 'Blue', value: '#e0ecff' },
]
