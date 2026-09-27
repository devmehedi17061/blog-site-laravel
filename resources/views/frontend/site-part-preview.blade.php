{{--
  Builders → Header & footer preview: the real layout, with the draft header
  or footer in place ($sitePartDraft) and a stand-in page between them.
  Rendered by SitePartController::preview into a sandboxed frame. Links and
  forms do nothing here; the ☰ button still works, so a phone menu can be tried.
--}}
@extends('frontend.layout')

@section('title', 'Header & footer preview')
@section('robots', 'noindex, nofollow')

@section('content')
  <div class="sp-preview-page">
    <p class="sp-preview-kicker">Preview</p>
    <h1>Your page content</h1>
    <p>This stands in for a page, so you can see the {{ $previewKind }} in place. Scroll to see a sticky header stay at the top, and try the ☰ button at phone size.</p>
    @for ($i = 0; $i < 3; $i++)
      <div class="sp-preview-block"></div>
    @endfor
  </div>

  <style>
    .sp-preview-page { display: flex; flex-direction: column; gap: 16px; }
    .sp-preview-kicker { margin: 0; font: 700 11px/1 var(--font-heading); letter-spacing: .12em; text-transform: uppercase; color: var(--color-accent); }
    .sp-preview-page h1 { margin: 0; font-size: 34px; }
    .sp-preview-page p { margin: 0; max-width: 560px; color: var(--color-neutral-700); }
    .sp-preview-block { height: 170px; background: repeating-linear-gradient(135deg, var(--color-neutral-100) 0 12px, var(--color-neutral-200) 12px 24px); }
  </style>

  <script>
    (function () {
      // Nothing leaves the preview.
      document.addEventListener('click', function (e) {
        if (e.target.closest('a')) e.preventDefault()
      }, true)
      document.addEventListener('submit', function (e) { e.preventDefault() }, true)

      // The frame is sandboxed and cannot keep anything itself, so the
      // builder keeps the scroll position across re-renders.
      if (window.__spScroll) window.scrollTo(0, window.__spScroll)
      window.addEventListener('scroll', function () { parent.postMessage({ spScroll: window.scrollY }, '*') }, { passive: true })
    })()
  </script>
@endsection
