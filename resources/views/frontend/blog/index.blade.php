@extends('frontend.layout')

@section('title', $site->title(__('Blog')))
@section('description', $site->get('default_description') ?: __('Latest posts from :site.', ['site' => $site->name()]))

@section('content')

  <div class="mb-8">
    <div class="kicker mb-1">{{ __('Blog') }}</div>
    <h1 class="!mb-2">
      @if ($query)
        {{ __('Results for “:q”', ['q' => $query]) }}
      @else
        {{ __('Latest posts') }}
      @endif
    </h1>
    <p class="text-[15px] text-neutral-700">
      {{ trans_choice(':count post published.|:count posts published.', $posts->total(), ['count' => $posts->total()]) }}
    </p>
  </div>

  @if ($categories->isNotEmpty())
    <nav class="chip-group mb-8" aria-label="{{ __('Categories') }}">
      <a href="{{ route('blog.index') }}" class="chip chip-active no-underline">{{ __('All') }}</a>
      {{-- Flat but in tree order; a sub-category is marked so the list
           reads as a hierarchy rather than a jumble of equals. --}}
      @foreach ($categories as $category)
        <a href="{{ route('blog.category', $category->slug) }}"
           class="chip no-underline @if ($category->depth > 0) chip-nested @endif"
           @if ($category->depth > 0) title="{{ __('In :category', ['category' => $category->parent?->name]) }}" @endif>
          @if ($category->depth > 0)<span class="chip-nest-mark" aria-hidden="true">&rsaquo;</span>@endif
          {{ $category->name }}
        </a>
      @endforeach
    </nav>
  @endif

  @if ($posts->isEmpty())
    <div class="panel max-w-[560px]">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('Nothing here yet') }}</div>
      <p class="panel-body text-sm text-neutral-700">
        {{ __('No published posts match this view.') }}
      </p>
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
