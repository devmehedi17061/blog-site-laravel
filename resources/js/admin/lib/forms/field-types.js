/**
 * What the builder knows about each field type: what to call it, what to say
 * about it, and which inputs the editor should show for it.
 *
 * `choices` decides whether the options list appears; `placeholder` whether a
 * placeholder makes sense — there is nowhere to put one on a checkbox.
 */
export const FIELD_TYPES = {
  text: { label: 'Text', mono: 'Ab', hint: 'One line of text.', placeholder: true },
  email: { label: 'Email', mono: '@', hint: 'Checked as an address before it is accepted.', placeholder: true },
  tel: { label: 'Phone', mono: '#', hint: 'A phone number, kept as typed.', placeholder: true },
  url: { label: 'Link', mono: '↗', hint: 'Must be a full address.', placeholder: true },
  number: { label: 'Number', mono: '12', hint: 'Digits only.', placeholder: true },
  date: { label: 'Date', mono: '📅', hint: 'A date picker in the browser.', placeholder: false },
  textarea: { label: 'Long text', mono: '¶', hint: 'A box several lines tall.', placeholder: true },
  select: { label: 'Dropdown', mono: '▾', hint: 'One answer from the list below.', placeholder: true, choices: true },
  radio: { label: 'Radio buttons', mono: '◉', hint: 'One answer, all options visible.', placeholder: false, choices: true },
  checkbox: {
    label: 'Checkbox',
    mono: '☑',
    hint: 'With options it is a multi-choice; with none it is a single yes/no, like agreeing to terms.',
    placeholder: false,
    choices: true,
  },
  file: { label: 'File upload', mono: '⇪', hint: 'Up to 5 MB, stored with the submission.', placeholder: false },
}

/** Mirrors FieldKeys on the server: names become payload keys. */
export function normaliseName(raw) {
  return String(raw || '')
    .replace(/[^a-zA-Z0-9_ ]/g, '')
    .trim()
    .replace(/\s+/g, '_')
    .toLowerCase()
    .replace(/^[0-9_]+/, '')
}

export function uniqueName(fields, base, ignore = null) {
  const taken = new Set(fields.filter((f) => f !== ignore).map((f) => f.name))
  const root = normaliseName(base) || 'field'

  if (!taken.has(root)) return root

  let n = 2
  while (taken.has(`${root}_${n}`)) n++

  return `${root}_${n}`
}

export function blankField(fields, type = 'text') {
  const label = `${FIELD_TYPES[type]?.label ?? 'New'} field`

  return {
    id: null,
    type,
    label,
    name: uniqueName(fields, label),
    placeholder: '',
    help: '',
    width: 'full',
    rule: '',
    required: false,
    options: FIELD_TYPES[type]?.choices ? ['First option', 'Second option'] : [],
  }
}

export function hasChoices(field) {
  return Boolean(FIELD_TYPES[field?.type]?.choices)
}

/** A lone checkbox is a yes/no rather than a list to pick from. */
export function isToggle(field) {
  return field?.type === 'checkbox' && (field.options ?? []).filter((o) => String(o).trim()).length === 0
}
