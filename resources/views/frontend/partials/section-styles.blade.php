@php
  /**
   * Every section's CSS for one page, collected into a single <style> in the
   * head. Each rule was already rewritten to sit under #section-{id}, so a
   * section can only ever style itself.
   *
   * @var iterable<\App\Models\Section> $sections
   */
  $renderer = app(\App\Support\SectionRenderer::class);

  $css = collect($sections)
      ->map(fn ($section) => $renderer->css($section).$renderer->visibilityCss($section))
      ->filter()
      ->implode("\n");
@endphp

@if ($css !== '')
  <style id="section-styles">
{!! $css !!}
  </style>
@endif
