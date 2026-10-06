@extends('frontend.layout')

@section('title', $course->meta_title ?: $site->title($course->title))
@section('description', $course->meta_description ?: ($course->subtitle ?? Str::limit(strip_tags((string) $course->description), 155)))
@section('canonical', route('courses.show', $course->slug))
@section('og_image', (string) ($course->thumbnail?->url))
@if ($previewing)
  @section('robots', 'noindex, nofollow')
@endif

@push('head')
  {{-- Course structured data, so search engines can show it as a course. --}}
  <script type="application/ld+json">
    {!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Course',
      'name' => $course->title,
      'description' => $course->meta_description ?: ($course->subtitle ?: Str::limit(strip_tags((string) $course->description), 300)),
      'image' => \App\Support\Seo::absolute($course->thumbnail?->url),
      'provider' => ['@type' => 'Organization', 'name' => $site->name(), 'sameAs' => url('/')],
      'url' => route('courses.show', $course->slug),
      'aggregateRating' => $rating ? ['@type' => 'AggregateRating', 'ratingValue' => $rating, 'reviewCount' => $reviews->count()] : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
  </script>
@endpush

{{-- The hero runs edge to edge, so the page builds its own column. --}}
@section('main_class', 'w-full')

@php
  $minutes = $course->sections->flatMap->lessons->sum('duration_minutes');
  $duration = $minutes >= 60 ? intdiv($minutes, 60).'h '.($minutes % 60).'m' : $minutes.' min';
  $isActive = $enrollment?->isActive();
  $loginFirst = fn () => route('student.login', ['redirect' => '/courses/'.$course->slug]);
@endphp

@section('content')

  @if ($previewing)
    <div class="no-print bg-warning-100 px-4 py-2 text-center text-[13px] font-semibold text-warning-800">
      This course is a draft — only people who can edit it see this page.
    </div>
  @endif

  <section class="course-hero">
    <div class="mx-auto max-w-[var(--site-width)] px-4 py-10 lg:py-14">
      <div class="lg:max-w-[calc(100%-380px)]">
        <nav class="mb-4 text-[13px] text-neutral-400" aria-label="Breadcrumb">
          <a href="{{ route('courses.index') }}" class="no-underline hover:underline">Courses</a>
          @if ($course->category)
            <span class="mx-2">/</span>
            <a href="{{ route('courses.index', ['category' => $course->category->slug]) }}" class="no-underline hover:underline">{{ $course->category->name }}</a>
          @endif
        </nav>

        <div class="kicker mb-2">{{ $course->level }} · {{ $course->language }}</div>
        <h1 class="!mb-3 text-[clamp(28px,4vw,42px)] leading-tight">{{ $course->title }}</h1>
        @if ($course->subtitle)
          <p class="mb-5 max-w-[680px] text-[17px] leading-relaxed text-neutral-200">{{ $course->subtitle }}</p>
        @endif

        <div class="course-meta !text-[13px]">
          @if ($rating)
            <span><strong class="text-[#e5a00d]">{{ number_format($rating, 1) }}</strong> @include('frontend.partials.stars', ['value' => $rating]) ({{ $reviews->count() }})</span>
          @endif
          <span>{{ number_format($course->enrollments_count) }} {{ Str::plural('student', $course->enrollments_count) }}</span>
          @if ($course->instructor)
            <span>By <strong class="text-white">{{ $course->instructor->name }}</strong></span>
          @endif
          <span>Updated {{ $site->date($course->updated_at) }}</span>
        </div>
      </div>
    </div>
  </section>

  <div class="mx-auto grid max-w-[var(--site-width)] gap-8 px-4 py-10 lg:grid-cols-[minmax(0,1fr)_350px]">

    {{-- the buy box: first on a phone, beside the content on a wide screen --}}
    <aside class="lg:order-2">
      <div class="buy-box panel shadow-md">
        @if ($course->intro_video && ($embed = (new \App\Models\Lesson(['video_url' => $course->intro_video]))->embedUrl()))
          <div class="video-frame"><iframe src="{{ $embed }}" title="Course introduction" allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" loading="lazy"></iframe></div>
        @elseif ($course->thumbnail)
          <img src="{{ $course->thumbnail->url }}" alt="{{ $course->thumbnail->alt_text ?: $course->title }}" class="aspect-video w-full border-b border-line object-cover" fetchpriority="high">
        @endif

        <div class="flex flex-col gap-4 p-5">
          @include('frontend.partials.flash', ['class' => ''])

          <div>
            @if ($course->isFree())
              <span class="price price-lg price-free">Free</span>
            @else
              <span class="price price-lg">{{ \App\Support\Lms::money($course->currentPrice()) }}</span>
              @if ($course->onSale())
                <span class="price-was">{{ \App\Support\Lms::money($course->price) }}</span>
                <span class="tag tag-accent ml-1">{{ round((1 - $course->currentPrice() / (float) $course->price) * 100) }}% off</span>
              @endif
            @endif
          </div>

          @if ($isActive)
            <a href="{{ route('learn.show', $course->slug) }}" class="btn btn-primary btn-lg w-full">
              {{ $enrollment->completed_at ? 'Review the course' : 'Continue learning' }} →
            </a>
            @if ($enrollment->completed_at && \App\Support\Lms::setting('certificates'))
              <a href="{{ route('student.certificate', $enrollment) }}" class="btn btn-secondary w-full">View your certificate</a>
            @endif
          @elseif ($enrollment?->status === 'Pending')
            <div class="alert !text-[13px]">
              <strong>Payment being checked.</strong> We will open the course as soon as it is confirmed.
            </div>
            <a href="{{ route('courses.checkout', $course->slug) }}" class="btn btn-secondary w-full">Update payment details</a>
          @elseif (! auth()->check())
            <a href="{{ $loginFirst() }}" class="btn btn-primary btn-lg w-full">
              {{ $course->isFree() ? 'Enroll for free' : 'Buy this course' }}
            </a>
            <p class="text-center text-[12px] text-neutral-600">
              Sign in or <a href="{{ route('student.register', ['redirect' => '/courses/'.$course->slug]) }}" class="text-accent">create a free account</a> to start.
            </p>
          @elseif ($course->isFree())
            <form method="POST" action="{{ route('courses.enroll', $course->slug) }}">
              @csrf
              <button class="btn btn-primary btn-lg w-full">Enroll for free</button>
            </form>
          @else
            <a href="{{ route('courses.checkout', $course->slug) }}" class="btn btn-primary btn-lg w-full">Buy this course</a>
          @endif

          <ul class="flex flex-col gap-2 border-t border-line pt-4 text-[13px] text-neutral-700">
            <li class="flex justify-between"><span>Lessons</span><strong class="text-ink">{{ $course->lessons_count }}</strong></li>
            @if ($minutes > 0)
              <li class="flex justify-between"><span>Total length</span><strong class="text-ink">{{ $duration }}</strong></li>
            @endif
            <li class="flex justify-between"><span>Level</span><strong class="text-ink">{{ $course->level }}</strong></li>
            <li class="flex justify-between"><span>Language</span><strong class="text-ink">{{ $course->language }}</strong></li>
            <li class="flex justify-between"><span>Access</span><strong class="text-ink">Lifetime</strong></li>
            @if (\App\Support\Lms::setting('certificates'))
              <li class="flex justify-between"><span>Certificate</span><strong class="text-ink">On completion</strong></li>
            @endif
          </ul>
        </div>
      </div>
    </aside>

    <div class="flex min-w-0 flex-col gap-10 lg:order-1">

      @if (! empty($course->outcomes))
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> What you will learn</div>
          <ul class="check-list p-5">
            @foreach ($course->outcomes as $outcome)
              <li>{{ $outcome }}</li>
            @endforeach
          </ul>
        </section>
      @endif

      <section>
        <div class="mb-3 flex flex-wrap items-end gap-2">
          <h2 class="!mb-0 text-[22px]">Course content</h2>
          <span class="ml-auto text-[13px] text-neutral-600">
            {{ $course->sections->count() }} {{ Str::plural('section', $course->sections->count()) }} ·
            {{ $course->lessons_count }} {{ Str::plural('lesson', $course->lessons_count) }}
            @if ($minutes > 0) · {{ $duration }} @endif
          </span>
        </div>

        @if ($course->sections->isEmpty())
          <p class="text-sm text-neutral-600">The curriculum is being written.</p>
        @else
          <div class="curriculum">
            @foreach ($course->sections as $section)
              <details @if ($loop->first) open @endif>
                <summary>
                  {{ $section->title }}
                  <span class="curriculum-count">{{ $section->lessons->count() }} {{ Str::plural('lesson', $section->lessons->count()) }}</span>
                </summary>
                @foreach ($section->lessons as $lesson)
                  @php $open = ($isActive || $lesson->is_preview) && ! in_array($lesson->id, $locked, true); @endphp
                  <{{ $open ? 'a' : 'div' }} class="lesson-row" @if ($open) href="{{ route('learn.show', [$course->slug, $lesson->id]) }}" @endif>
                    <span class="lesson-row-icon" aria-hidden="true">
                      @if ($lesson->type === 'video')
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4Z" fill="currentColor"/></svg>
                      @else
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                      @endif
                    </span>
                    <span>{{ $lesson->title }}</span>
                    @if ($lesson->is_preview && ! $isActive)
                      <span class="lesson-row-preview">Preview</span>
                    @endif
                    <span class="lesson-row-time">
                      @if (! $open)
                        <svg class="mr-1 inline" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-label="Locked"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                      @endif
                      {{ $lesson->durationLabel() }}
                    </span>
                  </{{ $open ? 'a' : 'div' }}>
                @endforeach
              </details>
            @endforeach
          </div>
        @endif
      </section>

      @if ($course->description)
        <section>
          <h2 class="!mb-3 text-[22px]">About this course</h2>
          <div class="post-body rich-content">{!! $course->description !!}</div>
        </section>
      @endif

      @if (! empty($course->requirements))
        <section>
          <h2 class="!mb-3 text-[22px]">Requirements</h2>
          <ul class="ml-5 flex list-disc flex-col gap-1.5 text-[15px] text-neutral-800">
            @foreach ($course->requirements as $requirement)
              <li>{{ $requirement }}</li>
            @endforeach
          </ul>
        </section>
      @endif

      @if ($course->instructor)
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Your instructor</div>
          <div class="flex items-center gap-4 p-5">
            <span class="grid h-14 w-14 flex-none place-items-center bg-neutral-900 font-heading text-[18px] font-bold text-white">{{ $course->instructor->initials }}</span>
            <div>
              <div class="font-heading text-[17px] font-bold">{{ $course->instructor->name }}</div>
              <div class="text-[13px] text-neutral-600">
                {{ $course->instructor->courses()->published()->count() }} {{ Str::plural('course', $course->instructor->courses()->published()->count()) }} on {{ $site->name() }}
              </div>
            </div>
          </div>
        </section>
      @endif

      @if (\App\Support\Lms::setting('reviews'))
        <section id="reviews">
          <div class="mb-4 flex items-center gap-3">
            <h2 class="!mb-0 text-[22px]">Student reviews</h2>
            @if ($rating)
              <span class="ml-auto flex items-center gap-2 text-[14px]">
                <strong class="font-heading text-[22px]">{{ number_format($rating, 1) }}</strong>
                @include('frontend.partials.stars', ['value' => $rating])
              </span>
            @endif
          </div>

          @if ($isActive)
            <form method="POST" action="{{ route('courses.review', $course->slug) }}" class="panel mb-5 flex flex-col gap-3 p-5">
              @csrf
              <div class="font-heading text-[14px] font-bold">{{ $myReview ? 'Update your review' : 'Rate this course' }}</div>
              <div class="star-input" role="radiogroup" aria-label="Your rating">
                @for ($i = 5; $i >= 1; $i--)
                  <input type="radio" id="star-{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating', $myReview?->rating) === $i) required>
                  <label for="star-{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">★</label>
                @endfor
              </div>
              @error('rating') <p class="field-error">{{ $message }}</p> @enderror
              <textarea name="comment" class="input !min-h-[90px] py-2" placeholder="What did you think? (optional)">{{ old('comment', $myReview?->comment) }}</textarea>
              <div><button class="btn btn-primary">{{ $myReview ? 'Update review' : 'Post review' }}</button></div>
            </form>
          @endif

          @forelse ($reviews as $review)
            <article class="border-b border-line py-4">
              <div class="mb-1.5 flex items-center gap-3">
                <span class="grid h-9 w-9 flex-none place-items-center bg-neutral-200 font-heading text-[12px] font-bold">{{ $review->student?->initials ?? '?' }}</span>
                <div>
                  <div class="text-[14px] font-semibold">{{ $review->student?->name ?? 'A student' }}</div>
                  <div class="flex items-center gap-2 text-[12px] text-neutral-600">
                    @include('frontend.partials.stars', ['value' => $review->rating]) {{ $review->created_at->diffForHumans() }}
                  </div>
                </div>
              </div>
              @if ($review->comment)
                <p class="pl-12 text-[14px] leading-relaxed text-neutral-800">{{ $review->comment }}</p>
              @endif
            </article>
          @empty
            <p class="text-sm text-neutral-600">No reviews yet{{ $isActive ? ' — be the first.' : '.' }}</p>
          @endforelse
        </section>
      @endif
    </div>
  </div>

  @if ($related->isNotEmpty())
    <section class="mx-auto max-w-[var(--site-width)] px-4 pb-14">
      <div class="mb-4 flex items-center gap-[10px]">
        <span class="h-3.5 w-3.5 bg-accent"></span>
        <h4 class="!mb-0">More in {{ $course->category?->name }}</h4>
      </div>
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($related as $item)
          @include('frontend.partials.course-card', ['course' => $item])
        @endforeach
      </div>
    </section>
  @endif

@endsection
