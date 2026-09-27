/**
 * Merges `extra` onto `base` while keeping getters as getters.
 *
 * Object spread ({ ...base }) would *call* every getter once and store the
 * result, so a computed property such as `resultCount` would freeze at its
 * value from before the first fetch. Copying property descriptors keeps the
 * getters live.
 */
export function extend(base, extra) {
  return Object.defineProperties(base, Object.getOwnPropertyDescriptors(extra))
}
