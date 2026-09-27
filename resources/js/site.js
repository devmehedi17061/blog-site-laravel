/**
 * The public site's own script (the admin has admin.js). Loaded on every
 * page and in the builders' previews.
 *
 * ── Sliders ──────────────────────────────────────────────────────────────
 * Any section can be a slider by marking up its parts; every option is a
 * data attribute on the root, so a library section wires them to fields:
 *
 *   <div data-slider data-autoplay="yes" data-interval="6" data-effect="slide"
 *        data-per-view="1" data-loop="yes" data-pause="yes">
 *     <div data-slider-track>
 *       <div data-slide>…</div>  <div data-slide>…</div>
 *     </div>
 *     <button data-slider-prev>‹</button> <button data-slider-next>›</button>
 *     <div data-slider-dots></div>
 *   </div>
 *
 *   data-autoplay   yes | no        moves on by itself
 *   data-interval   seconds         how long each slide stays (default 6)
 *   data-effect     slide | fade    how it changes
 *   data-per-view   1 | 2 | 3 | 4   slides side by side on a wide screen
 *                                   (at most 2 on a tablet, 1 on a phone)
 *   data-loop       yes | no        after the last comes the first
 *   data-pause      yes | no        autoplay waits while hovered or focused
 *
 * Swipe works on touch screens, and the arrow keys once the slider has
 * focus. Autoplay stops for people who ask for reduced motion, and while the
 * browser tab is hidden.
 */

const yes = (value, fallback) => (value === undefined || value === '' ? fallback : !/^(no|false|0|off)$/i.test(String(value).trim()))

function perViewFor(wanted) {
  const width = window.innerWidth
  if (width < 641) return 1
  if (width < 1024) return Math.min(2, wanted)
  return wanted
}

function initSlider(root) {
  if (root.dataset.sliderReady) return
  const track = root.querySelector('[data-slider-track]')
  const slides = track ? [...track.querySelectorAll(':scope > [data-slide]')] : []
  if (!track || slides.length === 0) return
  root.dataset.sliderReady = '1'

  // The track slides inside a clipping window of its own, so a padded card
  // around it never shows the edge of the next slide.
  if (!track.parentElement.classList.contains('sl-viewport')) {
    const viewport = document.createElement('div')
    viewport.className = 'sl-viewport'
    track.before(viewport)
    viewport.appendChild(track)
  }

  const o = root.dataset
  const effect = o.effect === 'fade' ? 'fade' : 'slide'
  const wantedPerView = effect === 'fade' ? 1 : Math.max(1, Math.min(4, parseInt(o.perView, 10) || 1))
  const loop = yes(o.loop, true)
  const pauseOnHover = yes(o.pause, true)
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const autoplay = yes(o.autoplay, false) && !reduced
  const interval = Math.max(2, parseFloat(o.interval) || 6) * 1000

  const prev = root.querySelector('[data-slider-prev]')
  const next = root.querySelector('[data-slider-next]')
  const dotsBox = root.querySelector('[data-slider-dots]')

  root.classList.add('sl', `sl-${effect}`)
  root.setAttribute('role', 'region')
  root.setAttribute('aria-roledescription', 'carousel')
  if (!root.hasAttribute('tabindex')) root.tabIndex = 0
  slides.forEach((slide, i) => {
    slide.setAttribute('role', 'group')
    slide.setAttribute('aria-roledescription', 'slide')
    slide.setAttribute('aria-label', `${i + 1} of ${slides.length}`)
  })

  let perView = perViewFor(wantedPerView)
  let index = 0
  let timer = null
  let paused = false

  const last = () => Math.max(0, slides.length - perView)

  function renderDots() {
    if (!dotsBox) return
    dotsBox.innerHTML = ''
    for (let i = 0; i <= last(); i++) {
      const dot = document.createElement('button')
      dot.type = 'button'
      dot.className = 'sl-dot'
      dot.setAttribute('aria-label', `Go to slide ${i + 1}`)
      dot.addEventListener('click', () => { go(i); restart() })
      dotsBox.appendChild(dot)
    }
  }

  function paint() {
    root.style.setProperty('--sl-per-view', perView)
    if (effect === 'slide') track.style.transform = `translateX(${-(index * 100) / perView}%)`

    slides.forEach((slide, i) => {
      const visible = i >= index && i < index + perView
      slide.classList.toggle('is-active', visible)
      slide.setAttribute('aria-hidden', visible ? 'false' : 'true')
      slide.inert = !visible
    })

    dotsBox?.querySelectorAll('.sl-dot').forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index)
      dot.setAttribute('aria-current', i === index ? 'true' : 'false')
    })

    if (prev) prev.disabled = !loop && index === 0
    if (next) next.disabled = !loop && index >= last()
    root.classList.toggle('sl-single', last() === 0)
  }

  function go(to) {
    const max = last()
    index = to > max ? (loop ? 0 : max) : to < 0 ? (loop ? max : 0) : to
    paint()
  }

  function tick() {
    if (!paused && !document.hidden) go(index + 1)
  }

  function restart() {
    clearInterval(timer)
    if (autoplay && last() > 0) timer = setInterval(tick, interval)
  }

  prev?.addEventListener('click', () => { go(index - 1); restart() })
  next?.addEventListener('click', () => { go(index + 1); restart() })

  if (pauseOnHover) {
    root.addEventListener('mouseenter', () => { paused = true })
    root.addEventListener('mouseleave', () => { paused = false })
    root.addEventListener('focusin', () => { paused = true })
    root.addEventListener('focusout', () => { paused = false })
  }

  root.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select')) return
    if (e.key === 'ArrowLeft') { go(index - 1); restart() }
    if (e.key === 'ArrowRight') { go(index + 1); restart() }
  })

  // Swipe: a horizontal drag of 40px or more turns the page.
  let startX = null
  track.addEventListener('pointerdown', (e) => { if (e.pointerType !== 'mouse') startX = e.clientX })
  track.addEventListener('pointerup', (e) => {
    if (startX === null) return
    const dx = e.clientX - startX
    startX = null
    if (Math.abs(dx) >= 40) { go(index + (dx < 0 ? 1 : -1)); restart() }
  })

  window.addEventListener('resize', () => {
    const now = perViewFor(wantedPerView)
    if (now === perView) return
    perView = now
    renderDots()
    go(Math.min(index, last()))
  })

  renderDots()
  paint()
  restart()
}

export function initSliders(scope = document) {
  scope.querySelectorAll('[data-slider]').forEach(initSlider)
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initSliders())
} else {
  initSliders()
}

window.initSliders = initSliders
