@extends('frontend.layout')

@section('title', $category->meta_title ?: $site->title($category->name))
@section('description', $category->meta_description ?: $category->description
    ?: 'Posts filed under '.$category->name.'.')
@section('og_image', $category->image?->url)

@section('content')

  <div class="mb-8">
    {{-- The trail is the category's own ancestry, so a sub-category says
         where it sits rather than pretending to be top level. --}}
    <nav class="mb-4 text-[13px] text-neutral-600" aria-label="Breadcrumb">
      <a href="{{ route('blog.index') }}" class="text-accent no-underline">Blog</a>

      @foreach ($ancestors as $ancestor)
        <span class="mx-2">/</span>
        <a href="{{ route('blog.category', $ancestor->slug) }}" class="text-accent no-underline">
          {{ $ancestor->name }}
        </a>
      @endforeach

      <span class="mx-2">/</span>
      <span>{{ $category->name }}</span>
    </nav>

    @if ($category->image)
      <img src="{{ $category->image->url }}" alt="{{ $category->name }}"
           class="mb-5 max-h-[260px] w-full border border-line-strong object-cover">
    @endif

    <div class="kicker mb-1">Category</div>
    <h1 class="!mb-2">{{ $category->name }}</h1>

    @if ($category->description)
      <p class="max-w-[680px] text-[15px] leading-relaxed text-neutral-700">{{ $category->description }}</p>
    @endif

    <p class="mt-2 text-[13px] text-neutral-600">
      {{ $posts->total() }} {{ Str::plural('post', $posts->total()) }}
      @if ($children->isNotEmpty())
        , including everything in its {{ $children->count() }}
        {{ Str::plural('sub-category', $children->count()) }}
      @endif
      .
    </p>

    @if ($children->isNotEmpty())
      <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($children as $child)
          <a href="{{ route('blog.category', $child->slug) }}" class="category-chip">
            {{ $child->name }}
            <span class="category-chip-count">{{ $child->posts_count }}</span>
          </a>
        @endforeach
      </div>
    @endif
  </div>

  @if ($posts->isEmpty())
    <div class="panel max-w-[560px]">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Nothing here yet</div>
      <p class="panel-body text-sm text-neutral-700">No published posts in {{ $category->name }} so far.</p>
    </div>
  @else
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ($posts as $post)
        @include('frontend.partials.post-card', ['post' => $post])
      @endforeach
    </div>

    <div class="mt-10">
      {{ $posts->links() }}
    </div>
  @endif

@endsection
