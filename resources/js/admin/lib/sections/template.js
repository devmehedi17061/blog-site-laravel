/**
 * The client half of App\Support\SectionRenderer. Everything here mirrors the
 * server so the builder's preview is what the published page shows:
 *
 *   {{ key }}                 a field's value, escaped by its type
 *   {{#group}} … {{/group}}   repeated once per item, with that item in scope
 */

/** The only field types a library section can use. */
export const FIELD_TYPES = {
  text: { label: 'Text', hint: 'One line of plain text.' },
  richtext: { label: 'Rich text', hint: 'Formatted HTML — bold, links and lists are kept.' },
  media: { label: 'Media', hint: 'Picked from the library; the token becomes the file URL.' },
  url: { label: 'Link', hint: 'A URL for an href or src.' },
  select: { label: 'Choice', hint: 'A drop-down of options you list, e.g. "yes, no" or "slide, fade". The token becomes the chosen one.' },
  form: { label: 'Form', hint: 'Shows a form from the form builder, wherever the token sits.' },
  group: { label: 'Group', hint: 'A repeatable set of fields — cards, logos, list rows.' },
}

const GROUP = /\{\{#\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}([\s\S]*?)\{\{\/\s*\1\s*\}\}/g
const TOKEN = /\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/g

/** Built here, never in Blade, which would compile a literal {{ … }}. */
export function tokenFor(key) {
  return `${'{'.repeat(2)} ${key ?? ''} ${'}'.repeat(2)}`
}

/** The pair that wraps a repeatable group in the HTML. */
export function groupTokens(key) {
  const open = '{'.repeat(2)
  const close = '}'.repeat(2)

  return { start: `${open}#${key}${close}`, end: `${open}/${key}${close}` }
}

export function normaliseKey(raw) {
  return String(raw || '')
    .replace(/[^a-zA-Z0-9_ ]/g, '')
    .trim()
    .replace(/\s+/g, '_')
    .toLowerCase()
    .replace(/^[0-9_]+/, '')
}

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/** @returns {Object<string, string>} field path => type */
export function typeMap(fields, prefix = '') {
  const map = {}

  for (const field of fields ?? []) {
    if (!field?.key) continue

    const path = prefix ? `${prefix}.${field.key}` : field.key

    if (field.type === 'group') {
      Object.assign(map, typeMap(field.fields ?? [], path))
      continue
    }

    map[path] = field.type || 'text'
  }

  return map
}

/**
 * Renders one block: groups first, so a token inside one sees that item.
 *
 * `globals` carries the page tokens down every level and gains the item
 * counters inside a group. Local values are merged over it, so a field always
 * beats a token of the same name.
 */
export function renderTemplate(source, values, types, prefix = '', globals = {}) {
  const withGroups = String(source ?? '').replace(GROUP, (_, key, inner) => {
    // A field's items first; otherwise a list the site supplies (latest_posts).
    const items = values?.[key] ?? globals?.[key]
    if (!Array.isArray(items)) return ''

    const childPrefix = prefix ? `${prefix}.${key}` : key

    return items
      .map((item, index) =>
        renderTemplate(
          inner,
          item && typeof item === 'object' ? item : {},
          types,
          childPrefix,
          { ...globals, _index: String(index), _number: String(index + 1), _count: String(items.length) }
        )
      )
      .join('')
  })

  // A supplied list is only for a group; it has no text of its own.
  const scalars = Object.fromEntries(Object.entries(globals).filter(([, value]) => !Array.isArray(value)))

  return fillTokens(withGroups, { ...scalars, ...escapedScope(values, types, prefix) })
}

/**
 * Stands in for the newest posts in the builders' previews — the real ones
 * are read on the server when the page renders (App\Support\LatestPosts).
 */
export function samplePosts(values = {}) {
  const n = Math.max(1, Math.min(12, parseInt(values?.posts_count, 10) || 3))
  // fallback_image, fallback_image_2, … take turns, as on the server.
  const fallbacks = Object.keys(values ?? {})
    .filter((key) => /^fallback_image(_\d+)?$/.test(key) && typeof values[key] === 'string' && values[key] !== '')
    .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
    .map((key) => values[key])

  return Array.from({ length: n }, (_, i) => ({
    title: `Your newest post ${i + 1} shows here`,
    url: '#',
    date: 'Today',
    category: 'Category',
    excerpt: 'The first lines of the post.',
    image: fallbacks.length ? fallbacks[i % fallbacks.length] : '',
  }))
}

export function fillTokens(source, values) {
  return String(source ?? '').replace(TOKEN, (_, key) => values[key] ?? '')
}

function escapedScope(values, types, prefix = '') {
  const out = {}

  for (const [key, value] of Object.entries(values ?? {})) {
    if (value && typeof value === 'object') continue // groups run before tokens

    const path = prefix ? `${prefix}.${key}` : key
    const type = types[path] ?? types[key] ?? 'text'

    out[key] = type === 'richtext' ? String(value ?? '') : escapeHtml(value)
  }

  return out
}

/**
 * Values for use inside CSS. Escaping would corrupt them here (&amp; is not a
 * colour), so only what could break out of a declaration is stripped.
 */
export function cssValues(values) {
  const out = {}

  for (const [key, value] of Object.entries(values ?? {})) {
    if (value && typeof value === 'object') continue
    out[key] = String(value ?? '').replace(/[{}<>]/g, '').trim()
  }

  return out
}

/**
 * The starting value object for a field tree: a group begins with one item,
 * and a field with a default begins filled in, so adding a section does not
 * mean retyping what it almost always says.
 */
export function blankValues(fields) {
  const out = {}

  for (const field of fields ?? []) {
    if (!field?.key) continue
    // A group starts with its sample items when it has some, else one blank.
    out[field.key] = field.type === 'group'
      ? (Array.isArray(field.items) && field.items.length ? field.items.map((item) => ({ ...blankItem(field.fields), ...item })) : [blankItem(field.fields)])
      : (field.default ?? '')
  }

  return out
}

export function blankItem(fields) {
  const item = {}

  for (const field of fields ?? []) {
    if (field?.key) item[field.key] = ''
  }

  return item
}

/** The plain text a section publishes, for the SEO reader to look at. */
export function renderSection(section, globals = {}) {
  if (section.mode !== 'Library') return String(section.html ?? '')

  const fields = section.template?.fields ?? []

  return renderTemplate(String(section.template?.html ?? ''), section.values ?? {}, typeMap(fields), '', globals)
}
