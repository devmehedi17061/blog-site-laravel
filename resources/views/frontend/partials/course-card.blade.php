{{-- One course in a grid: the catalogue, the homepage, "more like this". --}}
@php
  $rating = isset($course->reviews_avg_rating) ? round((float) $course->reviews_avg_rating, 1) : null;
@endphp
<article class="panel course-card transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
  <div class="flex items-center gap-2 bg-neutral-900 px-4 py-2.5 font-heading text-[10px] font-bold tracking-[0.1em] text-neutral-300 uppercase">
    <span class="h-2 w-2 bg-accent"></span>
    <span class="truncate">{{ $course->category?->name ?? __('Course') }}</span>
    <span class="ml-auto font-sans text-[11px] font-normal tracking-normal text-neutral-400 normal-case">{{ __($course->level) }}</span>
  </div>

  <a href="{{ route('courses.show', $course->slug) }}" class="course-card-thumb" tabindex="-1" aria-hidden="true">
    @if ($course->thumbnail)
      <img src="{{ $course->thumbnail->url }}" alt=""
           {!! \App\Support\ResponsiveImages::attributes($course->thumbnail, '(max-width: 720px) 100vw, 360px') !!}>
    @else
      <span class="course-card-thumb-blank">{{ mb_strtoupper(mb_substr($course->title, 0, 1)) }}</span>
    @endif
    @if ($course->is_featured)
      <span class="course-card-badge">{{ __('Featured') }}</span>
    @endif
  </a>

  <div class="flex flex-1 flex-col gap-2.5 p-5">
    <h3 class="!mb-0 text-[18px] leading-snug">
      <a href="{{ route('courses.show', $course->slug) }}" class="text-ink no-underline transition hover:text-accent">
        {{ $course->title }}
      </a>
    </h3>

    @if ($course->subtitle)
      <p class="flex-1 text-[13px] leading-relaxed text-neutral-600">{{ \Illuminate\Support\Str::limit($course->subtitle, 110) }}</p>
    @else
      <div class="flex-1"></div>
    @endif

    <div class="course-meta">
      @if ($course->instructor)
        <span>{{ $course->instructor->name }}</span>
      @endif
      @isset($course->lessons_count)
        <span>{{ trans_choice(':count lesson|:count lessons', $course->lessons_count, ['count' => $course->lessons_count]) }}</span>
      @endisset
      @if ($rating)
        <span>@include('frontend.partials.stars', ['value' => $rating]) {{ number_format($rating, 1) }}</span>
      @endif
    </div>

    <div class="mt-1 flex items-center gap-2 border-t border-line pt-3">
      @if ($course->isFree())
        <span class="price price-free">{{ __('Free') }}</span>
      @else
        <span class="price">{{ \App\Support\Lms::money($course->currentPrice()) }}</span>
        @if ($course->onSale())
          <span class="price-was">{{ \App\Support\Lms::money($course->price) }}</span>
        @endif
      @endif
      <a href="{{ route('courses.show', $course->slug) }}"
         class="ml-auto font-heading text-[12px] font-bold text-accent no-underline hover:text-accent-700">
        {{ __('View course') }} →
      </a>
    </div>
  </div>
</article>
