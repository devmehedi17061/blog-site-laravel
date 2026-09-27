@php
  /**
   * The site header: the one in use from Builders → Header & footer, or the
   * builder's unsaved draft while it previews ($sitePartDraft).
   *
   * @var array $menus  ['Header' => tree, 'Footer' => tree] from MenuComposer
   */
  $part = ($sitePartDraft['header'] ?? null) ?? \App\Models\SitePart::active('header');
  $out = $part ? app(\App\Support\SitePartRenderer::class)->render($part, $menus ?? []) : null;
@endphp
@if ($out)
  <style>{!! $out['css'] !!}</style>
  <header id="site-header" class="site-part site-header">{!! $out['html'] !!}</header>
  @include('frontend.partials.site-parts.script')
@endif
