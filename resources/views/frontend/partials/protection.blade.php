@php
  /**
   * Settings → Front end → Content protection, for this visitor.
   *
   * Deterrents only: they stop the casual right-click and copy, not anyone
   * who opens the page source. The Front end tab says so beside each switch.
   */
  $on = $site->protections();
@endphp

@if ($on)
  <style id="site-protection">
    @if (in_array('disable_selection', $on, true))
      body { -webkit-user-select: none; user-select: none; }
      /* Forms still have to be filled in, and copied from. */
      input, textarea, select, [contenteditable="true"] { -webkit-user-select: text; user-select: text; }
    @endif
    @if (in_array('disable_image_drag', $on, true))
      img { -webkit-user-drag: none; user-drag: none; }
    @endif
  </style>
  <script id="site-protection-js">
    (function (on) {
      if (on.indexOf('disable_right_click') !== -1) {
        document.addEventListener('contextmenu', function (e) {
          // Fields keep their menu: paste and spell-check live there.
          if (!e.target.closest('input, textarea, [contenteditable="true"]')) e.preventDefault()
        })
      }
      if (on.indexOf('block_devtools_keys') !== -1) {
        document.addEventListener('keydown', function (e) {
          var k = (e.key || '').toLowerCase()
          var ctrl = e.ctrlKey || e.metaKey
          if (k === 'f12' || (ctrl && e.shiftKey && (k === 'i' || k === 'j' || k === 'c')) || (ctrl && k === 'u')) {
            e.preventDefault()
          }
        })
      }
      if (on.indexOf('disable_image_drag') !== -1) {
        document.addEventListener('dragstart', function (e) {
          if (e.target.tagName === 'IMG') e.preventDefault()
        })
      }
    })(@json($on));
  </script>
@endif
