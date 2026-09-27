<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $site->isRtl() ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  @php
    // A view's own values win; the Site tab's defaults fill the gaps.
    // @section('x', $value) stores its value HTML-escaped; undo that once
    // here, so "Tips & Tricks" is not printed as "Tips &amp;amp; Tricks".
    $section = fn (string $name) => trim(html_entity_decode($__env->yieldContent($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $metaTitle = $section('title') ?: $site->homeTitle();
    $metaDescription = $site->description($section('description'));

    // What a share on Facebook, X, LinkedIn or WhatsApp shows: the page's
    // social fields when set, otherwise the same as the search result.
    $ogTitle = $section('og_title') ?: $metaTitle;
    $ogDescription = $section('og_description') ?: $metaDescription;
    $shareImage = (string) \App\Support\Seo::absolute($section('og_image') ?: (string) $site->get('share_image'));

    // One robots tag: the page's own choice, unless the whole site is hidden
    // (Settings → Site) or this is a preview — those always win.
    $robots = \App\Support\Seo::robots($section('robots') ?: null, $site->hiddenFromSearch(), $previewing ?? false);
  @endphp

  <title>{{ $metaTitle }}</title>
  @if ($metaDescription !== '')
    <meta name="description" content="{{ $metaDescription }}">
  @endif
  <link rel="canonical" href="@yield('canonical', url()->current())">
  <link rel="alternate" type="application/rss+xml" title="{{ $site->name() }}" href="{{ route('feed') }}">

  @if ($site->get('favicon'))
    <link rel="icon" href="{{ $site->get('favicon') }}">
  @endif

  {{-- Only said when it is not the default, which is what search engines assume. --}}
  @if ($robots !== 'index, follow')
    <meta name="robots" content="{{ $robots }}">
  @endif

  @if ($site->get('google_verification'))
    <meta name="google-site-verification" content="{{ $site->get('google_verification') }}">
  @endif
  @if ($site->get('bing_verification'))
    <meta name="msvalidate.01" content="{{ $site->get('bing_verification') }}">
  @endif

  {{-- Social previews --}}
  <meta property="og:site_name" content="{{ $site->name() }}">
  <meta property="og:locale" content="{{ $site->ogLocale() }}">
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:title" content="{{ $ogTitle }}">
  <meta name="twitter:title" content="{{ $ogTitle }}">
  @if ($ogDescription !== '')
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
  @endif
  <meta property="og:url" content="@yield('canonical', url()->current())">

  @if ($shareImage !== '')
    <meta property="og:image" content="{{ $shareImage }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <meta name="twitter:card" content="summary_large_image">
  @else
    <meta name="twitter:card" content="summary">
  @endif

  @stack('head')

  @vite(['resources/css/app.css', 'resources/js/site.js'])

  {{-- Settings → Design: fonts, colours and widths, over the stylesheet. --}}
  @include('frontend.partials.design')

  {{-- Settings → Front end → External files: stylesheets and head scripts
       (a CDN library, an icon font), after the site's own styles. --}}
  @foreach ($site->externals() as $file)
    @if ($file['type'] === 'css')
      <link rel="stylesheet" href="{{ $file['url'] }}">
    @elseif ($file['place'] === 'head')
      <script src="{{ $file['url'] }}" @if ($file['load'] !== 'normal') {{ $file['load'] }} @endif></script>
    @endif
  @endforeach

  {{-- Settings → Front end. Custom CSS comes after the site's own, so it wins. --}}
  @if ($site->customCss() !== '')
    <style id="site-custom-css">{!! $site->customCss() !!}</style>
  @endif

  @include('frontend.partials.protection')

  {{-- Site-wide head code (analytics, verification, pixels). Only a Super
       Admin can set it; it is printed exactly as entered. --}}
  {!! $site->frontend('head_code') !!}
</head>
{{-- A full-height column: the content grows to fill the screen, so on a
     short page the footer still sits at the very bottom, never halfway up. --}}
<body class="site-body flex min-h-screen flex-col bg-bg text-ink">

  {{-- Everything that belongs at the top of the screen, pinned there as the
       page scrolls — together, so the bars stack instead of overlapping. --}}
  {{-- Pinned unless the header builder says it should scroll away. --}}
  <div class="site-top @if (! ($showHeader ?? true) || ! ((($sitePartDraft['header'] ?? null) ?? \App\Models\SitePart::active('header'))?->sticky() ?? false)) is-static @endif">
    {{-- Only a page preview sets this: a bar saying what is being looked at,
         so a draft is never mistaken for the live site. --}}
    @if ($previewing ?? false)
      @include('frontend.partials.preview-bar')
    @endif

    {{-- Signed-in people get past maintenance mode; this says they did. --}}
    @if ($site->inMaintenance() && auth()->check())
      <div role="status" style="padding:8px 16px;background:#8a5a00;color:#fff;font:600 13px/1.4 Archivo,system-ui,sans-serif;text-align:center">
        Maintenance mode is on — visitors see the "back soon" page. You can see the site because you are signed in.
      </div>
    @endif

    {{-- A page's layout can drop the header, the footer, or both (a landing
         page). Every other view wears both. --}}
    @if ($showHeader ?? true)
      @include('frontend.partials.header')
    @endif
  </div>

  {{-- Most views want the usual centred column. A view that builds its own
       full-width bands — a page made of sections — overrides this. It
       grows to fill the screen (flex-1), which is what keeps the footer down. --}}
  <main class="flex-1 @yield('main_class', 'mx-auto w-full max-w-[var(--site-width)] px-4 py-10')">
    @yield('content')
  </main>

  @if ($showFooter ?? true)
    @include('frontend.partials.footer')
  @endif

  {{-- Settings → Front end → External files placed at the end of the page. --}}
  @foreach ($site->externals() as $file)
    @if ($file['type'] === 'js' && $file['place'] === 'body')
      <script src="{{ $file['url'] }}" @if ($file['load'] !== 'normal') {{ $file['load'] }} @endif></script>
    @endif
  @endforeach

  {{-- This page's or post's own JavaScript (the editor's Code tab). --}}
  @stack('body_end')

  {{-- Site-wide body code (chat widgets, deferred scripts). Super Admin only. --}}
  {!! $site->frontend('body_code') !!}

</body>
</html>
