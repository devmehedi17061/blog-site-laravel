@php
  /**
   * The site footer: the one in use from Builders → Header & footer, or the
   * builder's unsaved draft while it previews ($sitePartDraft).
   *
   * @var array $menus  ['Header' => tree, 'Footer' => tree] from MenuComposer
   */
  $part = ($sitePartDraft['footer'] ?? null) ?? \App\Models\SitePart::active('footer');
  $out = $part ? app(\App\Support\SitePartRenderer::class)->render($part, $menus ?? []) : null;
@endphp
@if ($out)
  <style>{!! $out['css'] !!}</style>
  <footer id="site-footer" class="site-part site-footer">{!! $out['html'] !!}</footer>
  @include('frontend.partials.site-parts.script')
@endif
