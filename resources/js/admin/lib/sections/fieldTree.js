import { normaliseKey } from './template.js'

/**
 * Editing a library section's field definitions. Keys become {{ tokens }},
 * so they have to stay unique within their level — a duplicate would
 * silently shadow its twin, exactly as it would server side.
 */

export function uniqueKey(fields, base = 'field', ignore = null) {
  const taken = new Set(
    (fields ?? []).filter((field) => field !== ignore).map((field) => field.key)
  )

  const root = normaliseKey(base) || 'field'
  if (!taken.has(root)) return root

  let n = 2
  while (taken.has(`${root}_${n}`)) n++

  return `${root}_${n}`
}

export function newField(fields, type = 'text') {
  const key = uniqueKey(fields, type === 'group' ? 'items' : 'field')

  const field = { key, label: type === 'group' ? 'Items' : 'New field', type }
  if (type === 'group') field.fields = [{ key: 'title', label: 'Title', type: 'text' }]

  return field
}

/** Moves an entry within its own list. Returns true when it moved. */
export function moveIn(list, index, direction) {
  const target = index + direction
  if (!list || target < 0 || target >= list.length) return false

  const [moved] = list.splice(index, 1)
  list.splice(target, 0, moved)

  return true
}

/** Groups nest one level only, which is what the server accepts. */
export function typesFor(allTypes, insideGroup) {
  return insideGroup ? allTypes.filter((type) => type !== 'group') : allTypes
}

/** Every token a template's HTML can use, flattened for the hint bar. */
export function tokenList(fields) {
  const out = []

  for (const field of fields ?? []) {
    if (!field?.key) continue

    if (field.type === 'group') {
      out.push({ key: field.key, group: true, label: field.label, fields: field.fields ?? [] })
      continue
    }

    out.push({ key: field.key, group: false, label: field.label })
  }

  return out
}
