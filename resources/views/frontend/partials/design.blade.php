@php
  /** Settings → Design, printed in the head of every public page. */
  $design = app(\App\Support\Design::class);
  $designValues = $designDraft ?? $design->values();
  $fontsUrl = $design->fontsUrl($designValues);
@endphp
@if ($fontsUrl)
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="{{ $fontsUrl }}" rel="stylesheet">
@endif
<style id="site-design">{!! $design->css($designValues) !!}</style>
