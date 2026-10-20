{{-- The {{ menu_toggle }} button: puts "menu-open" on the header or footer it
     sits in, which the part's own CSS uses (:root.menu-open …). Escape, a
     link in the open menu, or growing past the width where the button shows
     all close it again.

     Also, for the menu inside it:
       · each sub-menu's opener (.site-menu-expand, shown only by a header
         that folds its menu) puts "is-open" on its item — a header can show
         that as a screen sliding in — and every sub-menu gets a Back row
         (.site-menu-head: ‹ Back, and a title linking to the item's page);
       · a third-level flyout is kept on screen as it opens.
     Placed right after the part it serves. --}}
<script>
  (function () {
    var part = document.currentScript.previousElementSibling
    var sheet = part.querySelector('[data-menu-panel]')
    var BACK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg><span>Back</span>'

    function expander(node) { return node.querySelector(':scope > .site-menu-expand') }

    function openNode(node) {
      // The screen underneath goes back to its top, so the new one lines up.
      var under = node.parentElement.closest('.site-menu-panel') || sheet
      if (under) under.scrollTop = 0
      var panel = node.querySelector(':scope > .site-menu-panel')
      panel.scrollTop = 0
      node.classList.add('is-open')
      expander(node).setAttribute('aria-expanded', 'true')
      var back = panel.querySelector(':scope > .site-menu-head .site-menu-back')
      if (back) setTimeout(function () { back.focus({ preventScroll: true }) }, 340)
    }

    function closeNode(node) {
      node.classList.remove('is-open')
      var b = expander(node)
      b.setAttribute('aria-expanded', 'false')
      b.focus({ preventScroll: true })
    }

    function closeAll() {
      part.querySelectorAll('.site-menu-node.is-open').forEach(function (node) {
        node.classList.remove('is-open')
        expander(node).setAttribute('aria-expanded', 'false')
      })
    }

    part.querySelectorAll('.site-menu-node').forEach(function (node) {
      var panel = node.querySelector(':scope > .site-menu-panel')
      var button = expander(node)
      if (!panel || !button) return

      var link = node.querySelector(':scope > .site-menu-link')
      var text = link.querySelector('.site-menu-text')
      var head = document.createElement('div')
      head.className = 'site-menu-head'
      var back = document.createElement('button')
      back.type = 'button'
      back.className = 'site-menu-back'
      back.innerHTML = BACK
      var title = document.createElement(link.hasAttribute('href') ? 'a' : 'span')
      title.className = 'site-menu-title'
      title.textContent = (text || link).textContent.trim()
      if (link.hasAttribute('href')) title.href = link.getAttribute('href')
      head.appendChild(back)
      head.appendChild(title)
      panel.insertBefore(head, panel.firstChild)

      button.addEventListener('click', function () {
        node.classList.contains('is-open') ? closeNode(node) : openNode(node)
      })
      back.addEventListener('click', function () { closeNode(node) })
    })

    // A third-level flyout has to stay on screen. Measured as it opens (and
    // once on load and on resize, since a hidden one sticking out past the
    // edge would still widen the page), it tries in turn: as designed on the
    // right, to the left ("flip"), then as a single column ("one-col", for a
    // list the header splits in two) on the right and on the left.
    var placers = []
    part.querySelectorAll('.site-menu-panel .site-menu-node').forEach(function (node) {
      var sub = node.querySelector(':scope > .site-menu-panel')
      if (!sub) return
      function fits() {
        var r = sub.getBoundingClientRect()
        return r.left >= 8 && r.right <= document.documentElement.clientWidth - 8
      }
      function place() {
        node.classList.remove('flip', 'one-col')
        // Only a floating flyout; a folded phone menu stacks it in place.
        if (getComputedStyle(sub).position !== 'absolute') return
        var tries = [['flip'], ['one-col'], ['one-col', 'flip']]
        for (var i = 0; i < tries.length && !fits(); i++) {
          node.classList.remove('flip', 'one-col')
          tries[i].forEach(function (c) { node.classList.add(c) })
        }
        // Nothing fits (a very narrow window): one column on the right.
        if (!fits()) { node.classList.remove('flip'); node.classList.add('one-col') }
      }
      node.addEventListener('mouseenter', place)
      node.addEventListener('focusin', place)
      placers.push(place)
    })
    // In document order, so an outer flyout is placed before the ones inside it.
    function placeAll() { placers.forEach(function (place) { place() }) }
    if (placers.length) {
      placeAll()
      var resizing
      window.addEventListener('resize', function () { clearTimeout(resizing); resizing = setTimeout(placeAll, 150) })
      // Web fonts change the widths once they arrive.
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(placeAll)
    }

    var buttons = part.querySelectorAll('[data-menu-toggle]')
    if (!buttons.length) return

    function set(open) {
      part.classList.toggle('menu-open', open)
      // Every time it opens, it opens at the start.
      closeAll()
      if (sheet) sheet.scrollTop = 0
      buttons.forEach(function (b) {
        b.setAttribute('aria-expanded', open ? 'true' : 'false')
        b.setAttribute('aria-label', open ? 'Close the menu' : 'Open the menu')
      })
    }

    buttons.forEach(function (b) {
      b.addEventListener('click', function () { set(!part.classList.contains('menu-open')) })
    })
    part.addEventListener('click', function (e) {
      if (e.target.closest('[data-menu-panel] a')) set(false)
    })
    // Escape steps back one screen at a time, then closes the menu.
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || !part.classList.contains('menu-open')) return
      var open = part.querySelectorAll('.site-menu-node.is-open')
      if (open.length) closeNode(open[open.length - 1])
      else { set(false); buttons[0].focus() }
    })
    window.addEventListener('resize', function () {
      if (part.classList.contains('menu-open') && buttons[0].offsetParent === null) set(false)
    })
  })()
</script>
