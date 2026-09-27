/**
 * A tree edited as a flat list.
 *
 * Both the menu and the category tree are stored as parents and children but
 * *edited* as a flat array where each row carries its own depth. That is the
 * shape a drag-and-drop list actually has: moving a row is moving one element
 * of an array, and nesting it is changing one number. The server rebuilds the
 * parents from the same list.
 *
 * Two rules keep the list a legal tree at every moment, so an illegal one is
 * never even on screen:
 *
 *   1. A row can be at most one level deeper than the row above it.
 *   2. Nothing goes deeper than maxDepth.
 *
 * Every move takes the row's whole branch with it, which is the only
 * behaviour that is not surprising.
 */

/**
 * How many rows belong to the one at `index` — its children, their children,
 * and so on. A branch is everything below it that is deeper, stopping at the
 * first row back at the same level.
 */
export function branchLength(items, index) {
  const depth = items[index]?.depth ?? 0
  let size = 0

  for (let i = index + 1; i < items.length; i++) {
    if (items[i].depth <= depth) break
    size++
  }

  return size
}

/** The deepest a row may legally sit, given the row above it. */
export function maxDepthFor(items, index, maxDepth) {
  if (index === 0) return 0

  return Math.min(items[index - 1].depth + 1, maxDepth)
}

export function canIndent(items, index, maxDepth) {
  return Boolean(items[index]) && items[index].depth < maxDepthFor(items, index, maxDepth)
}

export function canOutdent(items, index) {
  return (items[index]?.depth ?? 0) > 0
}

export function shiftBranch(items, index, by, maxDepth) {
  const size = branchLength(items, index)

  for (let i = index; i <= index + size; i++) {
    items[i].depth = Math.max(0, Math.min(items[i].depth + by, maxDepth))
  }
}

/**
 * Pulls any row that is now too deep back into legality.
 *
 * Runs after every move, because the same rows can be legal in one place and
 * impossible in another — the list on screen is always a tree that could be
 * saved.
 */
export function normalise(items, maxDepth) {
  for (let i = 0; i < items.length; i++) {
    const ceiling = i === 0 ? 0 : Math.min(items[i - 1].depth + 1, maxDepth)
    items[i].depth = Math.max(0, Math.min(items[i].depth, ceiling))
  }
}

/** Moves a whole branch to a new index. Returns where it landed. */
export function moveBranch(items, from, to, maxDepth) {
  const size = branchLength(items, from)
  const branch = items.splice(from, size + 1)

  items.splice(to, 0, ...branch)
  normalise(items, maxDepth)

  return to
}

/** Up and down move over whole branches, never into the middle of one. */
export function moveUp(items, index, maxDepth) {
  if (index <= 0) return index

  let target = index - 1
  while (target > 0 && items[target].depth > items[index].depth) target--

  return moveBranch(items, index, target, maxDepth)
}

export function moveDown(items, index, maxDepth) {
  const size = branchLength(items, index)
  const next = index + size + 1

  if (next >= items.length) return index

  return moveBranch(items, index, next + branchLength(items, next) - size, maxDepth)
}

/**
 * Where a dragged branch should land when dropped on `index`.
 *
 * Dropping below the branch's own position has to account for the rows the
 * splice is about to remove, and dropping inside itself would detach the
 * branch from the list entirely.
 */
export function dropTarget(items, from, index) {
  const size = branchLength(items, from)

  if (index >= from && index <= from + size) return null

  return Math.max(0, index > from ? index - size : index)
}
