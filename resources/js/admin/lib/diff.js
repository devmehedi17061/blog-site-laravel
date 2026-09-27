/**
 * A small text diff for the revision history: lines first, then the words
 * inside each changed line, so a one-word edit in a long paragraph shows
 * as one word rather than the whole paragraph twice.
 */

/** Longest-common-subsequence edit script: [{ op: '=' | '-' | '+', value }]. */
function editScript(a, b) {
  let start = 0
  while (start < a.length && start < b.length && a[start] === b[start]) start++

  let endA = a.length
  let endB = b.length
  while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) {
    endA--
    endB--
  }

  const head = a.slice(0, start).map((value) => ({ op: '=', value }))
  const tail = a.slice(endA).map((value) => ({ op: '=', value }))
  const A = a.slice(start, endA)
  const B = b.slice(start, endB)

  // Too big to line up cheaply: show it as replaced wholesale.
  if (A.length * B.length > 2_000_000) {
    return [...head, ...A.map((value) => ({ op: '-', value })), ...B.map((value) => ({ op: '+', value })), ...tail]
  }

  const cols = B.length + 1
  const table = new Uint32Array((A.length + 1) * cols)
  for (let i = A.length - 1; i >= 0; i--) {
    for (let j = B.length - 1; j >= 0; j--) {
      table[i * cols + j] = A[i] === B[j]
        ? table[(i + 1) * cols + j + 1] + 1
        : Math.max(table[(i + 1) * cols + j], table[i * cols + j + 1])
    }
  }

  const middle = []
  let i = 0
  let j = 0
  while (i < A.length && j < B.length) {
    if (A[i] === B[j]) {
      middle.push({ op: '=', value: A[i] })
      i++
      j++
    } else if (table[(i + 1) * cols + j] >= table[i * cols + j + 1]) {
      middle.push({ op: '-', value: A[i++] })
    } else {
      middle.push({ op: '+', value: B[j++] })
    }
  }
  while (i < A.length) middle.push({ op: '-', value: A[i++] })
  while (j < B.length) middle.push({ op: '+', value: B[j++] })

  return [...head, ...middle, ...tail]
}

/** Neighbouring pieces with the same op become one run of text. */
function runs(script) {
  const out = []
  for (const { op, value } of script) {
    const last = out[out.length - 1]
    if (last && last.op === op) last.text += value
    else out.push({ op, text: value })
  }
  return out
}

/** Words and the spaces between them, so spacing survives the diff. */
export function diffWords(before, after) {
  return runs(editScript(before.split(/(\s+)/).filter(Boolean), after.split(/(\s+)/).filter(Boolean)))
}

/**
 * Line by line, from `before` to `after`. Each line is
 *   { kind: 'same' | 'changed' | 'added' | 'removed' | 'gap', parts: [{ op, text }] }
 * and long stretches of unchanged lines fold into one 'gap' line.
 */
export function diffText(before, after, context = 2) {
  const script = editScript(String(before ?? '').split('\n'), String(after ?? '').split('\n'))
  const lines = []

  for (let k = 0; k < script.length;) {
    if (script[k].op === '=') {
      lines.push({ kind: 'same', parts: [{ op: '=', text: script[k].value }] })
      k++
      continue
    }

    // A block of removed lines followed by added ones: pair them up, so a
    // reworded line reads as one line with its changed words marked.
    const removed = []
    const added = []
    while (k < script.length && script[k].op === '-') removed.push(script[k++].value)
    while (k < script.length && script[k].op === '+') added.push(script[k++].value)

    const paired = Math.min(removed.length, added.length)
    for (let p = 0; p < paired; p++) lines.push({ kind: 'changed', parts: diffWords(removed[p], added[p]) })
    for (const text of removed.slice(paired)) lines.push({ kind: 'removed', parts: [{ op: '-', text }] })
    for (const text of added.slice(paired)) lines.push({ kind: 'added', parts: [{ op: '+', text }] })
  }

  return foldUnchanged(lines, context)
}

function foldUnchanged(lines, context) {
  const near = lines.map(() => false)
  lines.forEach((line, i) => {
    if (line.kind === 'same') return
    for (let d = -context; d <= context; d++) if (lines[i + d]) near[i + d] = true
  })

  const out = []
  let hidden = 0
  lines.forEach((line, i) => {
    if (line.kind === 'same' && !near[i]) {
      hidden++
      return
    }
    if (hidden) out.push({ kind: 'gap', parts: [{ op: '=', text: `${hidden} unchanged line${hidden === 1 ? '' : 's'}` }] })
    hidden = 0
    out.push(line)
  })
  if (hidden) out.push({ kind: 'gap', parts: [{ op: '=', text: `${hidden} unchanged line${hidden === 1 ? '' : 's'}` }] })

  return out
}
