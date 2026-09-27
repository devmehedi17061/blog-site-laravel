@extends('frontend.layout')

{{-- The Code tab: this page's own CSS in the head, its JavaScript at the end. --}}
@if (trim((string) $post->custom_css) !== '')
  @push('head')
    <style id="page-custom-css">{!! \App\Support\CustomCode::css($post->custom_css) !!}</style>
  @endpush
@endif
@if (trim((string) $post->custom_js) !== '')
  @push('body_end')
    <script id="page-custom-js">{!! \App\Support\CustomCode::js($post->custom_js) !!}</script>
  @endpush
@endif

@section('title', $post->meta_title ?: $site->title($post->title))
@section('description', $post->meta_description ?: ($post->excerpt ?? Str::limit(strip_tags($post->body ?? ''), 155)))
@section('og_type', 'article')
@section('canonical', $post->canonical_url ?: route('blog.show', $post->slug))
{{-- Social fields fall back to the search ones in the layout; the image to
     the featured image, then the site's default share image. --}}
@section('og_title', $post->og_title)
@section('og_description', $post->og_description)
@section('og_image', $post->og_image ?: $post->featuredImage?->url)
@section('robots', $post->robots)

@push('head')

  {{-- Article structured data, so the post can show as a rich result. --}}
  <script type="application/ld+json">
    {!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'BlogPosting',
      'headline' => $post->meta_title ?: $post->title,
      'description' => $post->meta_description ?: $post->excerpt,
      'image' => \App\Support\Seo::absolute($post->og_image ?: $post->featuredImage?->url),
      'datePublished' => $post->published_at?->toIso8601String(),
      'dateModified' => $post->updated_at?->toIso8601String(),
      'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
      'publisher' => ['@type' => 'Organization', 'name' => $site->name()],
      'mainEntityOfPage' => route('blog.show', $post->slug),
      'wordCount' => str_word_count(strip_tags((string) $post->body)),
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
@endpush

@section('content')

  <article class="mx-auto max-w-[var(--content-width)]">

    <nav class="mb-6 text-[13px] text-neutral-600" aria-label="Breadcrumb">
      <a href="{{ route('blog.index') }}" class="text-accent no-underline">Blog</a>
      @if ($post->category)
        <span class="mx-2">/</span>
        <a href="{{ route('blog.category', $post->category->slug) }}" class="text-accent no-underline">
          {{ $post->category->name }}
        </a>
      @endif
    </nav>

    <header class="mb-7 border-b border-line-strong pb-6">
      <div class="kicker mb-2">{{ $post->category?->name ?? 'Uncategorised' }}</div>
      <h1 class="!mb-3">{{ $post->title }}</h1>

      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-neutral-600">
        <span>{{ $post->author?->name ?? 'Unknown' }}</span>
        <time datetime="{{ $post->published_at?->toDateString() }}">
          {{ $site->date($post->published_at) }}
        </time>
        <span>{{ max(1, (int) ceil(str_word_count(strip_tags((string) $post->body)) / 200)) }} min read</span>
      </div>
    </header>

    @if ($post->featuredImage)
      <figure class="mb-7">
        {{-- The first thing on the page: fetched straight away, not lazily. --}}
        <img src="{{ $post->featuredImage->url }}"
             alt="{{ $post->featuredImage->alt_text ?: $post->title }}"
             {!! \App\Support\ResponsiveImages::attributes($post->featuredImage, \App\Support\ResponsiveImages::POST_SIZES, lazy: false) !!}
             class="h-auto w-full border border-line-strong" fetchpriority="high">
      </figure>
    @endif

    @if ($post->excerpt)
      <p class="alert mb-7 !text-[15px]">{{ $post->excerpt }}</p>
    @endif

    <div class="post-body rich-content spacing-{{ $post->content_spacing ?? 'normal' }}">
      {!! \App\Support\ResponsiveImages::apply($post->body, \App\Support\ResponsiveImages::POST_SIZES) !!}
    </div>
  </article>

  @if ($related->isNotEmpty())
    <section class="mx-auto mt-14 max-w-[var(--site-width)]">
      <div class="mb-4 flex items-center gap-[10px]">
        <span class="h-3.5 w-3.5 bg-accent"></span>
        <h4 class="!mb-0">More in {{ $post->category?->name }}</h4>
      </div>
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($related as $item)
          @include('frontend.partials.post-card', ['post' => $item])
        @endforeach
      </div>
    </section>
  @endif

@endsection
