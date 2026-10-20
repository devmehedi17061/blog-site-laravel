/**
 * x-live-frame: a live preview that changes without blinking — the preview
 * of the section builder, the section library and the header & footer
 * builder.
 *
 *   <div class="lib-frame" :style="previewFrameStyle" x-live-frame="previewDoc"></div>
 *   <div class="hfb-frame" x-live-frame.0="previewHtml" data-sandbox="allow-scripts"></div>
 *
 * Binding :srcdoc straight to the HTML reloaded the frame on every key
 * press: each reload blanked it to white, fetched the site's stylesheets
 * and fonts again and restarted its scripts and animations, and typing
 * fast meant it never finished loading at all. Here:
 *
 *   - the HTML is put in only once typing has paused (300 ms, or the
 *     modifier's number: `.0` when the caller already waits, as the header
 *     & footer builder does for its server round trip), and not at all when
 *     it has not changed;
 *   - the new version loads in a hidden frame behind the one on show, and
 *     the two swap only once it has loaded, so the old preview stays in
 *     view until the new one is ready (a newer edit replaces a version
 *     still loading);
 *   - the new frame opens at the old one's scroll position when it can read
 *     it (a sandboxed frame cannot; the header & footer builder passes its
 *     scroll in the HTML itself).
 *
 * The element holds the frames, so size and scale it as the frame used to
 * be; they fill it. data-sandbox and data-title carry over to each frame.
 */
export function liveFrame(Alpine) {
  Alpine.directive('live-frame', (el, { expression, modifiers }, { evaluateLater, effect, cleanup }) => {
    const read = evaluateLater(expression)
    const number = modifiers.find((m) => /^\d+$/.test(m))
    const wait = number === undefined ? 300 : Number(number)

    if (getComputedStyle(el).position === 'static') el.style.position = 'relative'

    let front = null // the frame on show
    let back = null // the next version, loading out of sight
    let current // the HTML of the newest frame, shown or loading
    let timer

    const frame = () => {
      const f = document.createElement('iframe')
      if (el.dataset.sandbox !== undefined) f.setAttribute('sandbox', el.dataset.sandbox)
      f.title = el.dataset.title || 'Preview'
      f.style.cssText = 'position:absolute;inset:0;display:block;width:100%;height:100%;border:0;background:transparent'
      return f
    }

    const show = (html) => {
      if (html === current) return
      current = html
      back?.remove()
      back = null

      if (!html) {
        front?.remove()
        front = null
        return
      }

      const next = frame()
      if (!front) {
        front = next
        next.srcdoc = html
        el.appendChild(next)
        return
      }

      next.style.visibility = 'hidden'
      next.style.pointerEvents = 'none'
      next.addEventListener('load', () => {
        if (back !== next) return
        try {
          next.contentWindow.scrollTo(front.contentWindow.scrollX, front.contentWindow.scrollY)
        } catch {
          // A sandboxed frame keeps its scroll to itself.
        }
        next.style.visibility = ''
        next.style.pointerEvents = ''
        front.remove()
        front = next
        back = null
      }, { once: true })
      back = next
      next.srcdoc = html
      el.appendChild(next)
    }

    effect(() => read((value) => {
      const html = String(value ?? '')
      clearTimeout(timer)
      if (!front || wait === 0) show(html)
      else timer = setTimeout(() => show(html), wait)
    }))

    cleanup(() => {
      clearTimeout(timer)
      el.replaceChildren()
    })
  })
}
