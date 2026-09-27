/**
 * A section's box settings and CSS scoping — the client twin of
 * SectionRenderer::inlineStyle(), ::contentStyle() and ::scope().
 *
 * A section is two boxes. The **band** runs the full width of the page, so a
 * background colour or image reaches both edges; the **column** inside it
 * holds the words at a readable width. That is why the max width is a column
 * setting: put it on the band and it would crop the background with it.
 *
 * Every setting is a free text input rather than a preset, so an author can
 * type any CSS value the property accepts. The order below is the order the
 * declarations are written in, which is also the order the server uses.
 */
export const BAND_STYLE_FIELDS = [
  { key: 'background', property: 'background', label: 'Background colour', placeholder: '#ffffff, or a gradient' },
  { key: 'color', property: 'color', label: 'Text colour', placeholder: '#1c1a19' },
  { key: 'padding', property: 'padding', label: 'Padding', placeholder: '64px 32px' },
  { key: 'margin', property: 'margin', label: 'Margin', placeholder: '0 0 40px' },
  { key: 'border_radius', property: 'border-radius', label: 'Border radius', placeholder: '12px' },
]

/** Shown only once a background image has been chosen. */
export const BACKGROUND_IMAGE_FIELDS = [
  { key: 'background_size', property: 'background-size', label: 'Size', placeholder: 'cover' },
  { key: 'background_position', property: 'background-position', label: 'Position', placeholder: 'center' },
  { key: 'background_repeat', property: 'background-repeat', label: 'Repeat', placeholder: 'no-repeat' },
]

export const CONTENT_STYLE_FIELDS = [
  { key: 'max_width', property: 'max-width', label: 'Content width', placeholder: '1100px' },
  { key: 'align', property: 'text-align', label: 'Text align', placeholder: 'left, center, right' },
]

function safe(value) {
  return String(value ?? '').replace(/[{}<>]/g, '').trim()
}

/** A url going inside url("…"), where a quote or bracket would break out. */
function safeUrl(value) {
  return String(value ?? '').replace(/["'()\\{}<>\s]/g, '').trim()
}

function filled(value) {
  return value !== null && value !== undefined && String(value).trim() !== ''
}

/** Declarations the author wrote themselves, collapsed onto one line. */
function freeDeclarations(value) {
  return safe(value).replace(/\s+/g, ' ').replace(/^[;\s]+|[;\s]+$/g, '')
}

function declarationsFor(style, fields) {
  return fields
    .filter((field) => filled(style?.[field.key]))
    .map((field) => `${field.property}:${safe(style[field.key])}`)
}

/** The full-width band: background, padding, and the author's own CSS. */
export function inlineStyleFrom(style) {
  const out = declarationsFor(style, BAND_STYLE_FIELDS.slice(0, 2))

  // Longhand, not the `background` shorthand, so a colour set above still
  // shows behind an image that does not cover.
  if (filled(style?.background_image)) {
    out.push(`background-image:url("${safeUrl(style.background_image)}")`)
    out.push(`background-size:${safe(style.background_size) || 'cover'}`)
    out.push(`background-position:${safe(style.background_position) || 'center'}`)
    out.push(`background-repeat:${safe(style.background_repeat) || 'no-repeat'}`)
  }

  out.push(...declarationsFor(style, BAND_STYLE_FIELDS.slice(2)))

  if (filled(style?.custom)) out.push(freeDeclarations(style.custom))

  return out.filter(Boolean).join(';')
}

/** The column inside the band: how wide the content sits, and how. */
export function contentStyleFrom(style) {
  const out = []

  if (filled(style?.max_width)) {
    out.push(`max-width:${safe(style.max_width)}`, 'margin-left:auto', 'margin-right:auto')
  }

  if (filled(style?.align)) out.push(`text-align:${safe(style.align)}`)
  if (filled(style?.content_custom)) out.push(freeDeclarations(style.content_custom))

  return out.filter(Boolean).join(';')
}

/** Breakpoint visibility cannot be inline, so it becomes a scoped rule. */
export function visibilityCss(style, selector) {
  let css = ''

  if (style?.hide_mobile) css += `@media (max-width:640px){${selector}{display:none !important}}`
  if (style?.hide_desktop) css += `@media (min-width:641px){${selector}{display:none !important}}`

  return css
}

/**
 * Prefixes every rule with one selector, the way the server does, so a
 * section's CSS can never reach the rest of the page.
 */
export function scopeCss(css, prefix) {
  // Comments go first, as on the server: one before a rule would otherwise
  // be read as part of its selector, and a comma in it would split it.
  const source = String(css || '').replace(/\/\*[\s\S]*?\*\//g, '').trim()
  if (!source) return ''

  let out = ''
  let offset = 0

  while (offset < source.length) {
    const braceAt = source.indexOf('{', offset)
    if (braceAt === -1) break

    const selector = source.slice(offset, braceAt).trim()

    // Walk to the matching close brace so nested at-rules stay intact.
    let depth = 0
    let end = -1

    for (let i = braceAt; i < source.length; i++) {
      if (source[i] === '{') depth++
      else if (source[i] === '}') {
        depth--
        if (depth === 0) { end = i; break }
      }
    }

    if (end === -1) break

    const body = source.slice(braceAt + 1, end)

    if (selector.startsWith('@')) {
      out += /^@(media|supports|layer|container|scope)\b/i.test(selector)
        ? `${selector}{${scopeCss(body, prefix)}}`
        : `${selector}{${body}}`
    } else {
      out += `${prefixSelector(selector, prefix)}{${body}}`
    }

    offset = end + 1
  }

  return out
}

function prefixSelector(selector, prefix) {
  return selector
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => {
      if (/^(:root|html|body)$/i.test(part)) return prefix
      // `:root.menu-open .nav` — this section while it carries that class.
      if (/^(:root|html|body)(?=[.:[#])/i.test(part)) return part.replace(/^(:root|html|body)/i, prefix)
      return `${prefix} ${part}`
    })
    .join(',')
}
