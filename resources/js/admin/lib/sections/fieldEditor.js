import { Editor } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'

/**
 * A small rich text editor for one field.
 *
 * Two rules keep this working inside Alpine:
 *
 *   1. The editor lives in a closure, never on the component. Alpine wraps its
 *      properties in a Proxy, and ProseMirror compares objects by identity —
 *      a proxied editor throws "applying a mismatched transaction".
 *
 *   2. The toolbar reads a single `active` getter rather than calling a method
 *      per button. Method calls are not tracked as reactive dependencies, so
 *      the buttons would never light up.
 *
 * It is given a reader and a writer rather than a value, because the field it
 * edits may be a key on a group item that moves around as the author edits.
 */
export function fieldEditor(read, write, placeholder = 'Write something…') {
  let editor = null

  return {
    revision: 0,

    init() {
      editor = new Editor({
        element: this.$refs.surface,
        content: read() || '',
        extensions: [
          StarterKit.configure({
            heading: { levels: [3, 4] },
            link: {
              openOnClick: false,
              autolink: true,
              HTMLAttributes: { rel: 'noopener', target: '_blank' },
            },
          }),
          Placeholder.configure({ placeholder }),
        ],
        editorProps: {
          attributes: { class: 'field-editor-surface rich-content' },
        },
        onUpdate: ({ editor: instance }) => {
          this.revision++
          write(instance.getHTML())
        },
        onSelectionUpdate: () => this.revision++,
      })
    },

    destroy() {
      editor?.destroy()
      editor = null
    },

    /** Every state the toolbar needs, read in one pass so it stays reactive. */
    get active() {
      this.revision // the dependency that makes this recompute

      if (!editor) return {}

      return {
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        strike: editor.isActive('strike'),
        h3: editor.isActive('heading', { level: 3 }),
        bullet: editor.isActive('bulletList'),
        ordered: editor.isActive('orderedList'),
        link: editor.isActive('link'),
      }
    },

    run(command) {
      if (!editor) return

      const chain = editor.chain().focus()
      command(chain)
      this.revision++
    },

    toggle(name) {
      const commands = {
        bold: (chain) => chain.toggleBold().run(),
        italic: (chain) => chain.toggleItalic().run(),
        strike: (chain) => chain.toggleStrike().run(),
        h3: (chain) => chain.toggleHeading({ level: 3 }).run(),
        bullet: (chain) => chain.toggleBulletList().run(),
        ordered: (chain) => chain.toggleOrderedList().run(),
      }

      this.run(commands[name] ?? ((chain) => chain.run()))
    },

    async setLink() {
      if (!editor) return

      if (this.active.link) {
        this.run((chain) => chain.unsetLink().run())
        return
      }

      const href = (await this.$store.dialog.prompt({
        title: 'Add a link',
        label: 'Link address',
        placeholder: 'https://example.com or /about',
        confirmLabel: 'Add link',
      }))?.trim()
      if (!href) return

      this.run((chain) => chain.extendMarkRange('link').setLink({ href }).run())
    },
  }
}
