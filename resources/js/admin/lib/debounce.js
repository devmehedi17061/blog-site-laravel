/** Delays a call until the caller has been quiet for `wait` milliseconds. */
export function debounce(fn, wait = 350) {
  let timer

  return function (...args) {
    clearTimeout(timer)
    timer = setTimeout(() => fn.apply(this, args), wait)
  }
}
