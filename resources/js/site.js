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
 *   data-pause      yes | no        autoplay waits while hovered or keyboard-
 *                                   focused, then goes on with the time left
 *
 * Swipe works on touch screens, and the arrow keys once the slider has
 * focus. Autoplay stops for people who ask for reduced motion, and while the
 * browser tab is hidden.
 *
 * The dots are made by the script, unless the section writes its own: any
 * [data-slider-tab] inside [data-slider-dots] (one per slide, with a label or
 * a progress bar) is used as the dot for the slide at the same position. The
 * current one gets .is-active, the ones before it .is-past. The root carries
 * --sl-interval, while it plays by itself .sl-autoplay, and while it waits
 * .sl-paused — enough for a progress bar to fill (and hold) in step with the
 * slides using CSS alone.
 *
 * ── Reveal on scroll ─────────────────────────────────────────────────────
 * Any element with data-reveal is set to data-reveal="wait" by the script,
 * then to "in" once a fifth of it is on screen — so a section's CSS can
 * animate from [data-reveal="wait"] to [data-reveal="in"]. Without the
 * script (or for people who ask for reduced motion) it simply shows.
 *
 * ── Accordions ───────────────────────────────────────────────────────────
 * <details name="x"> already keeps one open at a time. Put data-open-first
 * on the box around them to have the first one open when the page loads.
 *
 * ── Video on click ───────────────────────────────────────────────────────
 * <div data-video data-src="https://youtu.be/…"> holding a poster and a
 * [data-video-play] button: the button swaps the poster for the playing
 * video. YouTube and Vimeo links play in their own player (YouTube's
 * no-cookie one); anything else is taken as a video file. The box gets
 * .is-playing, so its CSS can fade the poster and the button out.
 * With data-mode="animation" the button plays the box's own [data-motion]
 * animated tutorial instead (scenes, chapters and controls — startMotion).
 *
 * ── Counters ─────────────────────────────────────────────────────────────
 * <span data-count="500">500</span> counts up from 0 once it scrolls into
 * view. Text that is not a number is left as it is.
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
  const tabs = dotsBox ? [...dotsBox.querySelectorAll('[data-slider-tab]')] : []

  root.classList.add('sl', `sl-${effect}`)
  root.classList.toggle('sl-autoplay', autoplay)
  root.style.setProperty('--sl-interval', `${interval}ms`)
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
  let startedAt = 0
  let remaining = interval
  const why = { hover: false, focus: false, hidden: document.hidden }

  const last = () => Math.max(0, slides.length - perView)

  function renderDots() {
    if (!dotsBox) return

    // The section's own tabs: only hide the ones there is no stop for.
    if (tabs.length) {
      tabs.forEach((tab, i) => { tab.hidden = i > last() })
      return
    }

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

    const dots = tabs.length ? tabs : [...(dotsBox?.querySelectorAll('.sl-dot') ?? [])]
    dots.forEach((dot, i) => {
      dot.classList.toggle('is-active', i === index)
      dot.classList.toggle('is-past', i < index)
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

  // One timeout per slide, so a pause can resume with the time that was
  // left — the progress bar (paused by .sl-paused) stays in step with it.
  function schedule(ms) {
    clearTimeout(timer)
    startedAt = Date.now()
    remaining = ms
    timer = setTimeout(() => { go(index + 1); schedule(interval) }, ms)
  }

  // A fresh slide's full time; the current tab's bar starts again with it.
  function restart() {
    clearTimeout(timer)
    timer = null
    remaining = interval
    const tab = tabs[index]
    if (tab?.classList.contains('is-active')) {
      tab.classList.remove('is-active')
      void tab.offsetWidth
      tab.classList.add('is-active')
    }
    if (autoplay && last() > 0 && !paused) schedule(interval)
  }

  function setPaused(reason, on) {
    why[reason] = on
    const now = (pauseOnHover && (why.hover || why.focus)) || why.hidden
    if (now === paused) return
    paused = now
    root.classList.toggle('sl-paused', paused)
    if (!autoplay || last() === 0) return
    if (paused) {
      clearTimeout(timer)
      remaining = Math.max(0, remaining - (Date.now() - startedAt))
    } else {
      schedule(remaining)
    }
  }

  tabs.forEach((tab, i) => {
    if (tab.tagName === 'BUTTON' && !tab.hasAttribute('type')) tab.type = 'button'
    tab.addEventListener('click', () => { go(i); restart() })
  })

  prev?.addEventListener('click', () => { go(index - 1); restart() })
  next?.addEventListener('click', () => { go(index + 1); restart() })

  // Keyboard focus pauses; a mouse click on an arrow or tab does not (the
  // button keeps focus afterwards, which used to stop autoplay for good).
  root.addEventListener('mouseenter', () => setPaused('hover', true))
  root.addEventListener('mouseleave', () => setPaused('hover', false))
  root.addEventListener('focusin', (e) => setPaused('focus', e.target.matches(':focus-visible')))
  root.addEventListener('focusout', (e) => { if (!root.contains(e.relatedTarget)) setPaused('focus', false) })
  document.addEventListener('visibilitychange', () => setPaused('hidden', document.hidden))

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
  if (why.hidden) setPaused('hidden', true)
  restart()
}

export function initSliders(scope = document) {
  scope.querySelectorAll('[data-slider]').forEach(initSlider)
}

export function initAccordions(scope = document) {
  scope.querySelectorAll('[data-open-first]:not([data-accordion-ready])').forEach((box) => {
    box.dataset.accordionReady = '1'
    const first = box.querySelector('details')
    if (first) first.open = true
  })
}

/**
 * Tabs: in a [data-tabs] box, each [data-tab] button shows the [data-pane]
 * with the same value and hides the others (hidden panes load nothing —
 * a lazy map in one waits until its tab is chosen). Arrow keys move between
 * tabs. Without the script every pane simply shows, one after another.
 */
export function initTabs(scope = document) {
  scope.querySelectorAll('[data-tabs]:not([data-tabs-ready])').forEach((box) => {
    const tabs = [...box.querySelectorAll('[data-tab]')].filter((t) => t.closest('[data-tabs]') === box)
    const panes = [...box.querySelectorAll('[data-pane]')].filter((p) => p.closest('[data-tabs]') === box)
    if (!tabs.length) return
    box.dataset.tabsReady = '1'

    const show = (value, focus = false) => {
      tabs.forEach((tab) => {
        const on = tab.dataset.tab === value
        tab.setAttribute('aria-selected', on ? 'true' : 'false')
        tab.tabIndex = on ? 0 : -1
        if (on && focus) tab.focus()
      })
      panes.forEach((pane) => { pane.hidden = pane.dataset.pane !== value })
    }

    tabs.forEach((tab, i) => {
      const pane = panes.find((p) => p.dataset.pane === tab.dataset.tab)
      if (pane) {
        pane.id ||= `tabpane-${Math.random().toString(36).slice(2, 8)}`
        tab.setAttribute('aria-controls', pane.id)
      }
      tab.addEventListener('click', () => show(tab.dataset.tab))
      tab.addEventListener('keydown', (e) => {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return
        const next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]
        show(next.dataset.tab, true)
      })
    })
    show(tabs[0].dataset.tab)
  })
}

export function initReveal(scope = document) {
  const items = [...scope.querySelectorAll('[data-reveal]')].filter((el) => el.dataset.reveal !== 'in')
  if (!items.length) return

  const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  if (still || !('IntersectionObserver' in window)) {
    items.forEach((el) => { el.dataset.reveal = 'in' })
    return
  }

  // In when a fifth of it shows — or, for a box taller than the screen
  // (a long card list on a phone, where a fifth may never fit), when it
  // fills a quarter of the screen.
  const watcher = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      const enough = entry.intersectionRatio >= 0.2 || entry.intersectionRect.height >= window.innerHeight * 0.25
      if (!enough) return
      entry.target.dataset.reveal = 'in'
      watcher.unobserve(entry.target)
    })
  }, { threshold: [0, 0.05, 0.1, 0.2, 0.3, 0.5] })

  items.forEach((el) => {
    el.dataset.reveal = 'wait'
    watcher.observe(el)
  })
}

/** The address a player loads for $url, or null for a plain video file. */
function embedUrl(url) {
  const youtube = url.match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})/)
  if (youtube) return `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0&playsinline=1`
  const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/)
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1`
  return null
}

const fmtTime = (ms) => {
  const s = Math.max(0, Math.round(ms / 1000))
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

/**
 * An animated tutorial that plays like a video: [data-scene]s shown one
 * after another, each for its caption's data-seconds. A scene's CSS animates
 * off .is-on (added afresh each time the scene starts); .is-paused on the
 * root is for pausing those animations. The controls are found by their
 * data-motion-* attributes; the chapter strip is built here.
 */
function startMotion(root) {
  if (root.motion) { root.motion.replay(); return root.motion }

  const scenes = [...root.querySelectorAll('[data-scene]')]
  const caps = [...root.querySelectorAll('[data-cap]')]
  const durs = scenes.map((s, i) => Math.max(1, parseFloat(caps[i]?.dataset.seconds) || parseFloat(s.dataset.seconds) || 6) * 1000)
  const starts = durs.map((_, i) => durs.slice(0, i).reduce((a, b) => a + b, 0))
  const total = durs.reduce((a, b) => a + b, 0)
  const $ = (name) => root.querySelector(`[data-motion-${name}]`)
  const screen = $('screen')
  const track = $('track')
  const toggle = $('toggle')
  const time = $('time')
  const chapter = $('chapter')
  const title = $('title')
  const text = $('text')
  const sub = $('sub')

  // Scenes are drawn on a 1000px-wide canvas, scaled to the screen.
  const fit = () => root.style.setProperty('--k', String((screen?.clientWidth || 1000) / 1000))
  fit()
  if ('ResizeObserver' in window && screen) new ResizeObserver(fit).observe(screen)

  const fills = scenes.map((_, i) => {
    const seg = document.createElement('button')
    seg.type = 'button'
    seg.className = 'm-seg'
    seg.style.flexGrow = String(durs[i])
    seg.setAttribute('aria-label', `Chapter ${i + 1}: ${caps[i]?.querySelector('b')?.textContent || ''}`)
    const fill = document.createElement('i')
    seg.appendChild(fill)
    seg.addEventListener('click', () => seek(i))
    track?.appendChild(seg)
    return fill
  })

  let elapsed = 0
  let current = -1
  let playing = false
  let last = 0

  function show(i) {
    current = i
    scenes.forEach((s, j) => { if (j !== i) s.classList.remove('is-on') })
    const scene = scenes[i]
    scene.classList.remove('is-on')
    void scene.offsetWidth // restart its animations
    scene.classList.add('is-on')
    if (chapter) chapter.textContent = `Chapter ${i + 1} of ${scenes.length}`
    if (title) title.textContent = caps[i]?.querySelector('b')?.textContent || ''
    if (text) text.textContent = caps[i]?.querySelector('span')?.textContent || ''
    if (sub) { sub.classList.remove('is-new'); void sub.offsetWidth; sub.classList.add('is-new') }
  }

  function paint() {
    let i = starts.findIndex((s, j) => elapsed >= s && elapsed < s + durs[j])
    if (i < 0) i = scenes.length - 1
    if (i !== current) show(i)
    fills.forEach((f, j) => { f.style.width = `${Math.min(100, Math.max(0, ((elapsed - starts[j]) / durs[j]) * 100))}%` })
    if (time) time.textContent = `${fmtTime(elapsed)} / ${fmtTime(total)}`
  }

  function frame(now) {
    if (!playing) return
    elapsed += now - last
    last = now
    if (elapsed >= total) { elapsed = total; paint(); end(); return }
    paint()
    requestAnimationFrame(frame)
  }

  function play() {
    if (elapsed >= total) { replay(); return }
    playing = true
    root.classList.remove('is-paused', 'is-ended')
    toggle?.setAttribute('aria-label', 'Pause')
    toggle?.classList.remove('is-play')
    last = performance.now()
    requestAnimationFrame(frame)
  }

  function pause() {
    playing = false
    root.classList.add('is-paused')
    toggle?.setAttribute('aria-label', 'Play')
    toggle?.classList.add('is-play')
  }

  function end() {
    pause()
    root.classList.add('is-ended')
  }

  function seek(i) {
    elapsed = starts[i]
    current = -1
    root.classList.remove('is-ended')
    paint()
    if (!playing) play()
  }

  function replay() { seek(0) }

  toggle?.addEventListener('click', () => (playing ? pause() : play()))
  // A click on the picture pauses or resumes, as on any video.
  screen?.addEventListener('click', (e) => {
    if (e.target.closest('a, button') || root.classList.contains('is-ended')) return
    playing ? pause() : play()
  })
  $('restart')?.addEventListener('click', replay)
  root.querySelectorAll('[data-motion-replay]').forEach((b) => b.addEventListener('click', replay))
  $('full')?.addEventListener('click', () => {
    const box = root.closest('[data-video]') || root
    if (document.fullscreenElement) document.exitFullscreen?.()
    else box.requestFullscreen?.()
  })
  root.addEventListener('keydown', (e) => {
    if (e.key === ' ' || e.key === 'k') { e.preventDefault(); playing ? pause() : play() }
  })
  document.addEventListener('visibilitychange', () => { if (document.hidden && playing) pause() })

  root.hidden = false
  paint()
  play()
  root.motion = { play, pause, seek, replay, get elapsed() { return elapsed }, get playing() { return playing } }
  return root.motion
}

export function initVideos(scope = document) {
  scope.querySelectorAll('[data-video]:not([data-video-ready])').forEach((box) => {
    const button = box.querySelector('[data-video-play]')
    const src = (box.dataset.src || '').trim()
    const motion = box.dataset.mode === 'animation' ? box.querySelector('[data-motion]') : null
    if (!button) return
    box.dataset.videoReady = '1'
    if (!src && !motion) {
      button.hidden = true
      return
    }

    button.addEventListener('click', () => {
      if (box.classList.contains('is-playing')) return
      if (motion) {
        box.classList.add('is-playing', 'is-motion')
        startMotion(motion)
        motion.querySelector('[data-motion-toggle]')?.focus({ preventScroll: true })
        return
      }
      const embed = embedUrl(src)
      let player
      if (embed) {
        player = document.createElement('iframe')
        player.src = embed
        player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen'
        player.allowFullscreen = true
        player.title = button.getAttribute('aria-label') || 'Video'
      } else {
        player = document.createElement('video')
        player.src = src
        player.controls = true
        player.autoplay = true
        player.playsInline = true
      }
      player.className = 'video-player'
      box.appendChild(player)
      box.classList.add('is-playing')
      player.focus?.()
    })
  })
}

export function initCounters(scope = document) {
  const items = [...scope.querySelectorAll('[data-count]:not([data-count-ready])')]
  if (!items.length) return
  const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  const run = (el) => {
    const target = parseFloat(String(el.dataset.count).replace(/,/g, ''))
    if (!Number.isFinite(target)) return
    const decimals = (String(el.dataset.count).split('.')[1] || '').length
    const start = performance.now()
    const step = (now) => {
      const t = Math.min(1, (now - start) / 1800)
      const eased = 1 - Math.pow(1 - t, 3)
      el.textContent = (target * eased).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })
      if (t < 1) requestAnimationFrame(step)
    }
    requestAnimationFrame(step)
  }

  items.forEach((el) => { el.dataset.countReady = '1' })
  if (still || !('IntersectionObserver' in window)) return
  // Start from 0, so the number is not seen at its end before it counts.
  items.forEach((el) => { if (Number.isFinite(parseFloat(String(el.dataset.count).replace(/,/g, '')))) el.textContent = '0' })

  const watcher = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return
      watcher.unobserve(entry.target)
      run(entry.target)
    })
  }, { threshold: 0.5 })
  items.forEach((el) => watcher.observe(el))
}

/**
 * Filters: in a [data-filter] box, each [data-filter-btn="key"] shows only
 * the [data-filter-item]s whose space-separated keys include it ("all" shows
 * every one); a [data-filter-search] input narrows them by their text. Shown
 * items get .is-in afresh with --n (their order), so CSS can stagger them in.
 * Buttons get their count in [data-filter-count] and hide when they match
 * nothing. A #key in the address picks that filter on arrival.
 */
export function initFilters(scope = document) {
  scope.querySelectorAll('[data-filter]:not([data-filter-ready])').forEach((box) => {
    const buttons = [...box.querySelectorAll('[data-filter-btn]')]
    const items = [...box.querySelectorAll('[data-filter-item]')]
    const search = box.querySelector('[data-filter-search]')
    const empty = box.querySelector('[data-filter-empty]')
    if (!items.length) return
    box.dataset.filterReady = '1'

    const keys = (item) => String(item.dataset.filterItem || '').split(/\s+/)
    const matches = (item, key) => key === 'all' || keys(item).includes(key)
    buttons.forEach((b) => {
      const n = items.filter((item) => matches(item, b.dataset.filterBtn)).length
      const count = b.querySelector('[data-filter-count]')
      if (count) count.textContent = String(n)
      if (!n) b.hidden = true
    })

    let current = 'all'
    let query = ''
    const apply = (animate = true) => {
      let shown = 0
      items.forEach((item) => {
        const ok = matches(item, current) && (!query || item.textContent.toLowerCase().includes(query))
        item.hidden = !ok
        item.classList.remove('is-in')
        if (ok && animate) {
          item.style.setProperty('--n', String(Math.min(shown, 12)))
          void item.offsetWidth
          item.classList.add('is-in')
        }
        if (ok) shown++
      })
      if (empty) empty.hidden = shown > 0
      buttons.forEach((b) => b.setAttribute('aria-pressed', b.dataset.filterBtn === current ? 'true' : 'false'))
    }

    buttons.forEach((b) => b.addEventListener('click', () => { current = b.dataset.filterBtn; apply() }))
    search?.addEventListener('input', () => { query = search.value.trim().toLowerCase(); apply() })

    const fromHash = () => {
      const wanted = decodeURIComponent(location.hash.slice(1))
      return wanted && buttons.some((b) => b.dataset.filterBtn === wanted && !b.hidden) ? wanted : null
    }
    current = fromHash() || current
    apply(false)
    window.addEventListener('hashchange', () => {
      const wanted = fromHash()
      if (!wanted) return
      current = wanted
      apply()
      box.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  })
}

/**
 * Rotators: in a [data-rotate="seconds"] box, its children take turns — the
 * shown one has .is-on, the one leaving has .is-out for a moment, so CSS can
 * animate the change. Without the script (or with reduced motion) the first
 * one simply stays.
 */
export function initRotators(scope = document) {
  scope.querySelectorAll('[data-rotate]:not([data-rotate-ready])').forEach((box) => {
    const items = [...box.children]
    box.dataset.rotateReady = '1'
    if (!items.length) return
    items[0].classList.add('is-on')
    if (items.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const ms = Math.max(1.5, parseFloat(box.dataset.rotate) || 2.5) * 1000
    let i = 0
    setInterval(() => {
      if (document.hidden) return
      const leaving = items[i]
      leaving.classList.remove('is-on')
      leaving.classList.add('is-out')
      setTimeout(() => leaving.classList.remove('is-out'), 700)
      i = (i + 1) % items.length
      items[i].classList.add('is-on')
    }, ms)
  })
}

/**
 * Clocks: a [data-clock] box with data-tz (an IANA time zone), data-open and
 * data-close (hours, 0–23) and data-days (0 = Sunday, commas) shows that
 * place's time in its [data-clock-time] and, in [data-clock-state], its
 * data-open-text or data-closed-text; the box gets data-clock-state="open"
 * or "closed" for CSS. Updated every 30 seconds.
 */
export function initClocks(scope = document) {
  const boxes = [...scope.querySelectorAll('[data-clock]:not([data-clock-ready])')]
  if (!boxes.length) return
  const names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

  const tick = () => boxes.forEach((box) => {
    let parts
    try {
      parts = new Intl.DateTimeFormat('en-US', { timeZone: box.dataset.tz, weekday: 'short', hour: 'numeric', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date())
    } catch {
      return
    }
    const get = (type) => parts.find((p) => p.type === type)?.value ?? ''
    const hour = Number(get('hour')) % 24
    const minute = get('minute')
    const days = String(box.dataset.days || '1,2,3,4,5').split(',').map((d) => Number(d.trim()))
    const open = days.includes(names.indexOf(get('weekday')))
      && hour + Number(minute) / 60 >= (parseFloat(box.dataset.open) || 9)
      && hour + Number(minute) / 60 < (parseFloat(box.dataset.close) || 18)

    const time = box.querySelector('[data-clock-time]')
    if (time) time.textContent = `${hour % 12 || 12}:${minute} ${hour < 12 ? 'AM' : 'PM'}`
    const state = box.querySelector('[data-clock-state]')
    if (state) state.textContent = open ? (state.dataset.openText || 'Open now') : (state.dataset.closedText || 'Closed now')
    box.dataset.clockState = open ? 'open' : 'closed'
  })

  boxes.forEach((box) => { box.dataset.clockReady = '1' })
  tick()
  setInterval(tick, 30000)
}

/**
 * Pickers: [data-pick] buttons in a [data-pick-group="field"] box choose the
 * matching option (by its value, ignoring case) in the form select whose
 * name is that field — "service" or "fields[service]" — in the same section.
 * The chosen button gets aria-pressed; choosing in the select updates them.
 */
export function initPickers(scope = document) {
  scope.querySelectorAll('[data-pick-group]:not([data-pick-ready])').forEach((group) => {
    const field = group.dataset.pickGroup || 'service'
    const area = group.closest('.site-section') || document
    const select = area.querySelector(`select[name="${field}"], select[name="fields[${field}]"]`)
    const buttons = [...group.querySelectorAll('[data-pick]')]
    group.dataset.pickReady = '1'
    if (!select) return

    const optionFor = (wanted) => [...select.options].find((o) => o.value.trim().toLowerCase() === String(wanted).trim().toLowerCase())
    const mark = () => buttons.forEach((b) => b.setAttribute('aria-pressed', optionFor(b.dataset.pick)?.value === select.value && select.value !== '' ? 'true' : 'false'))

    buttons.forEach((b) => {
      if (!optionFor(b.dataset.pick)) return
      b.addEventListener('click', () => {
        select.value = optionFor(b.dataset.pick).value
        select.dispatchEvent(new Event('change', { bubbles: true }))
        select.classList.remove('is-picked')
        void select.offsetWidth
        select.classList.add('is-picked')
        const r = select.getBoundingClientRect()
        if (r.top < 80 || r.bottom > innerHeight) select.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    })
    select.addEventListener('change', mark)
    mark()
  })
}

/**
 * An article page's helpers: [data-read-progress] fills (its <i>) as the
 * reader moves through [data-toc-source]; [data-toc] lists that source's h2
 * and h3 headings as links (shown when there are two or more) and marks the
 * one being read; [data-copy-link] copies the page's address.
 */
export function initArticle(scope = document) {
  const source = scope.querySelector('[data-toc-source]')
  const bar = scope.querySelector('[data-read-progress] i')
  if (source && bar) {
    const update = () => {
      const r = source.getBoundingClientRect()
      const done = Math.min(1, Math.max(0, (innerHeight * 0.35 - r.top) / Math.max(1, r.height)))
      bar.style.transform = `scaleX(${done})`
    }
    addEventListener('scroll', update, { passive: true })
    addEventListener('resize', update)
    update()
  }

  const toc = scope.querySelector('[data-toc]')
  const headings = source ? [...source.querySelectorAll('h2, h3')] : []
  if (toc && headings.length >= 2) {
    const list = toc.querySelector('ol')
    const used = new Set()
    const links = headings.map((h) => {
      let id = h.id || (h.textContent || '').toLowerCase().trim().replace(/[^\w]+/g, '-').replace(/^-|-$/g, '') || 'section'
      while (used.has(id) || (document.getElementById(id) && document.getElementById(id) !== h)) id += '-2'
      used.add(id)
      h.id = id
      const li = document.createElement('li')
      if (h.tagName === 'H3') li.className = 'is-sub'
      const a = document.createElement('a')
      a.href = `#${id}`
      a.textContent = h.textContent
      li.appendChild(a)
      list.appendChild(li)
      return a
    })
    toc.hidden = false
    if ('IntersectionObserver' in window) {
      const watcher = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
          if (!e.isIntersecting) return
          links.forEach((a) => a.classList.toggle('is-current', a.getAttribute('href') === `#${e.target.id}`))
        })
      }, { rootMargin: '-15% 0px -70% 0px' })
      headings.forEach((h) => watcher.observe(h))
    }
  }

  scope.querySelectorAll('[data-copy-link]').forEach((b) => b.addEventListener('click', async () => {
    const note = b.querySelector('.nba-copied')
    try {
      await navigator.clipboard.writeText(location.href)
      if (note) note.textContent = 'Link copied'
    } catch {
      if (note) note.textContent = location.href
    }
    b.classList.add('is-done')
    setTimeout(() => { b.classList.remove('is-done'); if (note) note.textContent = '' }, 2200)
  }))
}

function init() {
  initArticle()
  initClocks()
  initPickers()
  initRotators()
  initFilters()
  initSliders()
  initAccordions()
  initReveal()
  initTabs()
  initVideos()
  initCounters()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}

window.initSliders = initSliders
window.initAccordions = initAccordions
window.initReveal = initReveal
window.initTabs = initTabs
window.initVideos = initVideos
window.initCounters = initCounters
window.initFilters = initFilters
window.initRotators = initRotators
window.initClocks = initClocks
window.initPickers = initPickers
window.initArticle = initArticle
