{{-- The {{ menu_toggle }} button: puts "menu-open" on the header or footer it
     sits in, which the part's own CSS uses (:root.menu-open …). Escape, a
     link in the open menu, or growing past the width where the button shows
     all close it again. Placed right after the part it serves. --}}
<script>
  (function () {
    var part = document.currentScript.previousElementSibling
    var buttons = part.querySelectorAll('[data-menu-toggle]')
    if (!buttons.length) return

    function set(open) {
      part.classList.toggle('menu-open', open)
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
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && part.classList.contains('menu-open')) { set(false); buttons[0].focus() }
    })
    window.addEventListener('resize', function () {
      if (part.classList.contains('menu-open') && buttons[0].offsetParent === null) set(false)
    })
  })()
</script>
