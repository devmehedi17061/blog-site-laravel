@extends('frontend.layout')

{{-- The Code tab: this page's own CSS in the head, its JavaScript at the end. --}}
@if (trim((string) $page->custom_css) !== '')
  @push('head')
    <style id="page-custom-css">{!! \App\Support\CustomCode::css($page->custom_css) !!}</style>
  @endpush
@endif
@if (trim((string) $page->custom_js) !== '')
  @push('body_end')
    <script id="page-custom-js">{!! \App\Support\CustomCode::js($page->custom_js) !!}</script>
  @endpush
@endif

{{-- The homepage reads "Site — Tagline" unless it has an SEO title of its own. --}}
@section('title', $page->meta_title ?: ($page->isHome() ? $site->homeTitle() : $site->title($page->title)))
@section('description', $page->meta_description ?: ($page->excerpt ?? ''))
@section('canonical', $page->canonical_url ?: url($page->path()))
@section('og_title', $page->og_title)
@section('og_description', $page->og_description)
@section('og_image', $page->og_image ?: $page->featuredImage?->url)
@section('robots', $page->robots)

@push('head')

  <script type="application/ld+json">
    {!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'WebPage',
      'name' => $page->meta_title ?: $page->title,
      'description' => $page->meta_description ?: $page->excerpt,
      'image' => \App\Support\Seo::absolute($page->og_image ?: $page->featuredImage?->url),
      'datePublished' => $page->published_at?->toIso8601String(),
      'dateModified' => $page->updated_at?->toIso8601String(),
      'url' => url($page->path()),
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
  @include('frontend.partials.section-styles', ['sections' => $page->sections])
@endpush

{{-- Sections set their own width, so the page is not boxed in by the layout. --}}
@section('main_class', 'w-full')

@section('content')
  {{-- No title band of its own. The page is exactly the sections it was built
       from, so nothing appears that the builder did not put there — a page
       that wants a heading and a trail adds the Breadcrumb section, which
       reads the page's own title through {{ page_title }}. --}}
  <article class="page-article">
    @forelse ($page->sections as $section)
      @include('frontend.partials.section', ['section' => $section])
    @empty
      <p class="page-notice text-[15px] text-neutral-600">This page has no sections yet.</p>
    @endforelse
  </article>
@endsection
