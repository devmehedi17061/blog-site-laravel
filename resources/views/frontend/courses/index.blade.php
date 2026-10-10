@extends('frontend.layout')

@section('title', $site->title(__('Courses')))
@section('description', __('Browse every course on :site — learn at your own pace.', ['site' => $site->name()]))

@section('content')

  @include('frontend.partials.flash')

  <div class="mb-8 flex flex-wrap items-end gap-4">
    <div class="min-w-0 flex-1">
      <div class="kicker mb-1">{{ __('Courses') }}</div>
      <h1 class="!mb-2">
        @if ($filters['q'])
          {{ __('Courses matching “:q”', ['q' => $filters['q']]) }}
        @else
          {{ __('Learn something new') }}
        @endif
      </h1>
      <p class="text-[15px] text-neutral-700">
        {{ trans_choice(':count course to choose from.|:count courses to choose from.', $courses->total(), ['count' => $courses->total()]) }}
      </p>
    </div>

    <form method="GET" action="{{ route('courses.index') }}" class="flex w-full max-w-[380px] gap-2" role="search">
      @foreach (['category', 'level', 'price', 'sort'] as $keep)
        @if ($filters[$keep] && $keep !== 'q')
          <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
        @endif
      @endforeach
      <input type="search" name="q" value="{{ $filters['q'] }}" class="input" placeholder="{{ __('Search courses…') }}" aria-label="{{ __('Search courses') }}">
      <button class="btn btn-primary">{{ __('Search') }}</button>
    </form>
  </div>

  {{-- Filters: plain links, so every view has an address of its own. --}}
  @php
    $link = fn (array $change) => route('courses.index', array_filter(array_merge($filters, $change, ['page' => null])));
  @endphp
  <div class="panel mb-8">
    <div class="flex flex-col gap-3 p-4">
      @if ($categories->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
          <span class="toolbar-label w-[70px]">{{ __('Topic') }}</span>
          <div class="chip-group">
            <a href="{{ $link(['category' => null]) }}" class="chip no-underline {{ ! $filters['category'] ? 'chip-active' : '' }}">{{ __('All') }}</a>
            @foreach ($categories as $category)
              <a href="{{ $link(['category' => $category->slug]) }}"
                 class="chip no-underline {{ $filters['category'] === $category->slug ? 'chip-active' : '' }}">{{ $category->name }}</a>
            @endforeach
          </div>
        </div>
      @endif

      <div class="flex flex-wrap items-center gap-2">
        <span class="toolbar-label w-[70px]">{{ __('Level') }}</span>
        <div class="chip-group">
          <a href="{{ $link(['level' => null]) }}" class="chip no-underline {{ ! $filters['level'] ? 'chip-active' : '' }}">{{ __('Any') }}</a>
          @foreach ($levels as $level)
            <a href="{{ $link(['level' => $level]) }}" class="chip no-underline {{ $filters['level'] === $level ? 'chip-active' : '' }}">{{ __($level) }}</a>
          @endforeach
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <span class="toolbar-label w-[70px]">{{ __('Price') }}</span>
        <div class="chip-group">
          @foreach (['' => 'Any', 'free' => 'Free', 'paid' => 'Paid'] as $value => $label)
            <a href="{{ $link(['price' => $value ?: null]) }}" class="chip no-underline {{ (string) $filters['price'] === $value ? 'chip-active' : '' }}">{{ __($label) }}</a>
          @endforeach
        </div>

        <form method="GET" action="{{ route('courses.index') }}" class="ml-auto flex items-center gap-2">
          @foreach (['q', 'category', 'level', 'price'] as $keep)
            @if ($filters[$keep])
              <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
            @endif
          @endforeach
          <label for="sort" class="toolbar-label">{{ __('Sort') }}</label>
          <select id="sort" name="sort" class="input !h-[32px] w-[160px] !text-[13px]" onchange="this.form.submit()">
            @foreach (['newest' => 'Newest', 'popular' => 'Most popular', 'price-low' => 'Price: low to high', 'price-high' => 'Price: high to low'] as $value => $label)
              <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ __($label) }}</option>
            @endforeach
          </select>
          <noscript><button class="btn btn-secondary btn-sm">{{ __('Go') }}</button></noscript>
        </form>
      </div>
    </div>
  </div>

  @if ($courses->isEmpty())
    <div class="panel max-w-[560px]">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('No courses here yet') }}</div>
      <p class="panel-body text-sm text-neutral-700">
        {{ __('Nothing matches this view.') }}
        <a href="{{ route('courses.index') }}" class="text-accent">{{ __('See every course') }}</a>.
      </p>
    </div>
  @else
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
      @foreach ($courses as $course)
        @include('frontend.partials.course-card', ['course' => $course])
      @endforeach
    </div>

    <div class="mt-10">
      {{ $courses->links() }}
    </div>
  @endif

@endsection
