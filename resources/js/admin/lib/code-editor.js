import { basicSetup } from 'codemirror'
import { EditorView, keymap } from '@codemirror/view'
import { EditorState } from '@codemirror/state'
import { indentWithTab } from '@codemirror/commands'
import { startCompletion } from '@codemirror/autocomplete'
import { html } from '@codemirror/lang-html'
import { css } from '@codemirror/lang-css'
import { javascript } from '@codemirror/lang-javascript'
import { abbreviationTracker, emmetConfig } from '@emmetio/codemirror6-plugin'

/**
 * x-code: turns a textarea into a code editor (CodeMirror 6) — the way the
 * HTML and CSS boxes of the section library and the header & footer builder
 * are edited.
 *
 *   <textarea x-model="draft.html" x-code="{ lang: 'html', tokens: () => codeTokens }"></textarea>
 *
 * What it gives, VS Code style:
 *   - HTML tag and attribute, and CSS property and value, suggestions
 *   - tags close themselves; brackets and quotes pair up
 *   - the cursor on a tag highlights its partner (open ↔ close)
 *   - Emmet: type  div.card>h3+p  and press Tab
 *   - {{ tokens }}: type {{ and the fields and site tokens are offered
 *   - line numbers, folding, search (Ctrl+F), multiple cursors, undo
 *
 * The textarea stays the source of truth for Alpine: edits are written back
 * to it with an input event (so x-model and @input="touch()" run as before),
 * and a value set from outside (another section opened) is pulled in.
 * `textarea._cm` is the editor view, for inserting at the cursor.
 */
export function codeEditor(Alpine) {
  Alpine.directive('code', (el, { expression }, { evaluate, effect, cleanup }) => {
    const options = expression ? evaluate(expression) : {}
    const lang = ['css', 'js'].includes(options.lang) ? options.lang : 'html'
    // Fixed when the editor is made: e.g. JavaScript for anyone but a Super Admin.
    const readOnly = !!options.readonly
    const tokens = typeof options.tokens === 'function' ? options.tokens : () => []

    let applyingOutside = false
    // Made once: the editor tells sources apart by identity, and a new one
    // on every lookup would leave the list waiting on it forever.
    const tokenSource = tokenCompletions(tokens)

    const view = new EditorView({
      state: EditorState.create({
        doc: el.value ?? '',
        extensions: [
          // Emmet first, so Tab expands an abbreviation before it indents.
          lang === 'js' ? [] : abbreviationTracker(),
          lang === 'js' ? [] : emmetConfig.of({ syntax: lang }),
          basicSetup,
          keymap.of([indentWithTab]),
          lang === 'css' ? css() : lang === 'js' ? javascript() : html({ autoCloseTags: true }),
          EditorState.readOnly.of(readOnly),
          EditorView.editable.of(!readOnly),
          EditorState.languageData.of(() => [{ autocomplete: tokenSource }]),
          EditorView.lineWrapping,
          theme,
          EditorView.updateListener.of((update) => {
            if (!update.docChanged || applyingOutside) return
            el.value = update.state.doc.toString()
            el.dispatchEvent(new Event('input', { bubbles: true }))

            // The list only opens by itself after a letter; "{{" is not one,
            // so the token list is opened here.
            if (update.transactions.some((tr) => tr.isUserEvent('input'))) {
              const head = update.state.selection.main.head
              const before = update.state.doc.sliceString(Math.max(0, head - 3), head)
              if (/\{\{[#/]?$/.test(before)) queueMicrotask(() => startCompletion(update.view))
            }
          }),
        ],
      }),
    })

    const wrap = document.createElement('div')
    wrap.className = 'code-editor'
    wrap.appendChild(view.dom)
    el.after(wrap)
    el.style.display = 'none'
    el._cm = view

    // Follow the model when it changes from outside (a different section
    // is opened, a starter is loaded). x-model runs before this directive.
    effect(() => {
      const value = el._x_model ? el._x_model.get() ?? '' : el.value
      const current = view.state.doc.toString()
      if (value === current) return

      applyingOutside = true
      view.dispatch({ changes: { from: 0, to: current.length, insert: String(value) } })
      applyingOutside = false
    })

    cleanup(() => {
      view.destroy()
      wrap.remove()
      delete el._cm
    })
  })
}

/** Puts text at the cursor of the editor behind a textarea (or at its end). */
export function insertAtCursor(textarea, text) {
  const view = textarea?._cm
  if (!view) return false

  view.dispatch(view.state.replaceSelection(text))
  view.focus()
  return true
}

/**
 * After {{ the fields and tokens are offered. Picking one writes the whole
 * token, and eats a }} the bracket-closer may already have typed.
 *
 * Each token: { key, detail?, info?, group? } — group: true writes {{#key}}{{/key}}.
 */
function tokenCompletions(getTokens) {
  return (context) => {
    const before = context.matchBefore(/\{\{\s*[#/]?\s*[a-zA-Z0-9_]*/)
    if (!before) return null

    const list = getTokens() ?? []
    if (!list.length) return null

    return {
      from: before.from,
      filter: false,
      options: list
        .filter((token) => {
          const typed = before.text.replace(/^\{\{\s*[#/]?\s*/, '').toLowerCase()
          return !typed || token.key.toLowerCase().includes(typed)
        })
        .map((token) => ({
          label: token.group ? `{{#${token.key}}}` : `{{ ${token.key} }}`,
          detail: token.detail ?? '',
          info: token.info,
          type: token.group ? 'keyword' : 'variable',
          apply: (view, completion, from, to) => {
            const after = view.state.doc.sliceString(to, to + 2)
            const end = after === '}}' ? to + 2 : after.startsWith('}') ? to + 1 : to
            const text = token.group
              ? `{{#${token.key}}}\n  \n{{/${token.key}}}`
              : `{{ ${token.key} }}`
            const cursor = token.group ? from + `{{#${token.key}}}\n  `.length : from + text.length

            view.dispatch({ changes: { from, to: end, insert: text }, selection: { anchor: cursor } })
          },
        })),
    }
  }
}

const theme = EditorView.theme({
  '&': {
    fontSize: '13px',
    backgroundColor: '#ffffff',
    border: '1px solid var(--color-line-strong)',
  },
  '&.cm-focused': { outline: 'none', borderColor: 'var(--color-accent)' },
  '.cm-scroller': {
    minHeight: '300px',
    maxHeight: '560px',
    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
    lineHeight: '1.6',
  },
  '.cm-content': { padding: '8px 0' },
  '.cm-gutters': { backgroundColor: 'var(--color-muted)', borderRight: '1px solid var(--color-line)', color: 'var(--color-neutral-500)' },
  '.cm-activeLine': { backgroundColor: 'rgb(236 48 19 / 0.04)' },
  '.cm-activeLineGutter': { backgroundColor: 'rgb(236 48 19 / 0.08)', color: 'var(--color-ink)' },
  // The partner of the tag or bracket under the cursor.
  '&.cm-focused .cm-matchingBracket': { backgroundColor: 'rgb(47 111 237 / 0.18)', outline: '1px solid rgb(47 111 237 / 0.5)' },
  '&.cm-focused .cm-nonmatchingBracket': { backgroundColor: 'rgb(236 48 19 / 0.2)' },
  '.cm-tooltip.cm-tooltip-autocomplete': { border: '1px solid var(--color-line-strong)', boxShadow: '0 8px 24px rgb(0 0 0 / 0.12)' },
  '.cm-tooltip-autocomplete > ul > li[aria-selected]': { backgroundColor: 'var(--color-accent)', color: '#fff' },
  '.cm-completionDetail': { marginLeft: '10px', fontStyle: 'normal', opacity: 0.65 },
})
