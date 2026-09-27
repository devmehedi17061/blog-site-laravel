@php
  /** @var \App\Models\Section $section */
  $renderer = app(\App\Support\SectionRenderer::class);
  $band = $renderer->inlineStyle($section);
  $content = $renderer->contentStyle($section);
@endphp

{{-- Two wrappers on purpose. The outer band runs the full width of the page,
     so a background colour or image reaches both edges; the inner column is
     where the max width holds the words at a readable measure. The id sits on
     the band because the section's own CSS is scoped to it, and that
     stylesheet is printed once, in the page head. --}}
<section id="section-{{ $section->id }}" class="site-section"
  @if ($band !== '') style="{{ $band }}" @endif>
  <div class="site-section-inner" @if ($content !== '') style="{{ $content }}" @endif>
    {!! \App\Support\ResponsiveImages::apply($renderer->html($section, $page ?? null)) !!}
  </div>
</section>
