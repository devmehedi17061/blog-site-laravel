@extends('frontend.student.shell')

@section('title', $site->title('My dashboard'))
@section('panel_kicker', now()->format('l, j F'))
@section('panel_title')
  {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ Str::before($user->name, ' ') }} 👋
@endsection

@section('panel')
@php $maxDay = max(1, collect($week)->max('count')); @endphp

  {{-- what needs the student: payments rejected or still being checked --}}
  @foreach ($rejectedPayments as $payment)
    <div class="pay-alert is-rejected mb-3">
      <strong>Payment for “{{ $payment->course?->title }}” was not verified.</strong>
      <span>{{ $payment->rejection_reason }}</span>
      <a href="{{ route('courses.checkout', $payment->course->slug) }}" class="mt-1 font-semibold text-accent-800 underline">Send the payment details again →</a>
    </div>
  @endforeach
  @foreach ($pendingPayments as $payment)
    <div class="pay-alert is-pending mb-3">
      <strong>We are verifying your payment for “{{ $payment->course?->title }}”.</strong>
      <span>{{ \App\Support\Lms::money($payment->amount) }} · {{ $payment->method }} · {{ $payment->transaction_id }} — sent {{ $payment->created_at->diffForHumans() }}. You will be notified as soon as it is checked.</span>
    </div>
  @endforeach

  {{-- continue where you left off --}}
  @if ($continue)
    @php $pct = $progress[$continue->id] ?? 0; $c = $continue->course; @endphp
    <a href="{{ $continue->last_lesson_id ? route('learn.show', [$c->slug, $continue->last_lesson_id]) : route('learn.show', $c->slug) }}"
       class="sp-continue mb-6 no-underline">
      <div class="sp-continue-thumb">
        @if ($c->thumbnail)
          <img src="{{ $c->thumbnail->url }}" alt="">
        @else
          <span class="course-card-thumb-blank">{{ mb_strtoupper(mb_substr($c->title, 0, 1)) }}</span>
        @endif
        <span class="sp-continue-play"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15l13-7.5Z"/></svg></span>
      </div>
      <div class="sp-continue-body">
        <div class="kicker !text-accent-300">{{ $continue->last_activity_at ? 'Continue where you left off · '.$continue->last_activity_at->diffForHumans() : 'Start learning' }}</div>
        <div class="font-heading text-[22px] font-extrabold leading-tight">{{ $c->title }}</div>
        @if ($continue->lastLesson)
          <div class="text-[14px] text-neutral-300">Last lesson: {{ $continue->lastLesson->title }}</div>
        @endif
        <div class="flex items-center gap-3">
          <div class="progress flex-1"><span style="width: {{ $pct }}%"></span></div>
          <strong class="font-heading text-[14px]">{{ $pct }}%</strong>
        </div>
        <span class="btn btn-primary self-start">{{ $pct > 0 ? 'Resume lesson' : 'Start the course' }} →</span>
      </div>
    </a>
  @endif

  <div class="sp-tiles mb-6">
    <div class="sp-tile is-accent"><strong>{{ $stats['in_progress'] }}</strong><span>In progress</span></div>
    <div class="sp-tile"><strong>{{ $stats['completed'] }}</strong><span>Completed</span></div>
    <div class="sp-tile"><strong>{{ $stats['lessons'] }}</strong><span>Lessons done</span></div>
    <div class="sp-tile"><strong>{{ $stats['hours'] }}</strong><span>Time learned</span></div>
    <div class="sp-tile is-dark"><strong>{{ $stats['streak'] }} 🔥</strong><span>Day streak</span></div>
  </div>

  <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="flex min-w-0 flex-col gap-6">

      {{-- the courses being learned --}}
      <section class="panel">
        <div class="panel-head">
          <span class="h-2.5 w-2.5 bg-accent"></span> Continue learning
          <a href="{{ route('student.courses') }}" class="ml-auto text-[12px] font-semibold normal-case tracking-normal text-accent-300 no-underline">All my courses →</a>
        </div>
        @forelse ($inProgress->take(4) as $enrollment)
          @php $pct = $progress[$enrollment->id] ?? 0; $c = $enrollment->course; @endphp
          <div class="sp-row">
            <span class="grid h-12 w-20 flex-none place-items-center overflow-hidden bg-neutral-900 font-heading font-extrabold text-accent">
              @if ($c->thumbnail) <img src="{{ $c->thumbnail->url }}" alt="" class="h-full w-full object-cover"> @else {{ mb_strtoupper(mb_substr($c->title, 0, 1)) }} @endif
            </span>
            <div class="min-w-[180px] flex-1">
              <a href="{{ route('learn.show', $c->slug) }}" class="font-heading text-[15px] font-bold text-ink no-underline hover:text-accent">{{ $c->title }}</a>
              <div class="mt-1.5 flex items-center gap-3">
                <div class="progress flex-1"><span style="width: {{ $pct }}%"></span></div>
                <span class="w-10 text-right text-[12px] font-bold">{{ $pct }}%</span>
              </div>
            </div>
            <a href="{{ route('learn.show', $c->slug) }}" class="btn btn-secondary btn-sm">{{ $pct ? 'Continue' : 'Start' }}</a>
          </div>
        @empty
          <div class="sp-empty">
            Nothing in progress right now.
            <div class="mt-3"><a href="{{ route('courses.index') }}" class="btn btn-primary">Find a course</a></div>
          </div>
        @endforelse
      </section>

      {{-- the last seven days --}}
      <section class="panel">
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> This week
          <span class="ml-auto text-[12px] font-semibold normal-case tracking-normal text-neutral-400">{{ collect($week)->sum('count') }} lessons finished</span>
        </div>
        <div class="p-5">
          <div class="sp-week" role="img" aria-label="Lessons finished on each of the last seven days">
            @foreach ($week as $day)
              <div class="sp-week-day {{ $day['today'] ? 'is-today' : '' }}" title="{{ $day['date'] }}: {{ $day['count'] }} lesson(s)">
                <b>{{ $day['count'] ?: '' }}</b>
                <div class="sp-week-bar {{ $day['count'] ? 'has' : '' }}" style="height: {{ $day['count'] ? max(8, round($day['count'] / $maxDay * 100)) : 4 }}%"></div>
                <span>{{ $day['today'] ? 'Today' : $day['label'] }}</span>
              </div>
            @endforeach
          </div>
          <p class="mt-4 text-[13px] text-neutral-600">
            @if ($stats['streak'] > 1)
              {{ $stats['streak'] }} days in a row — keep the streak going with one lesson today.
            @elseif ($stats['streak'] === 1)
              You learned today or yesterday. One lesson a day builds a streak.
            @else
              Finish a lesson today to start a streak.
            @endif
          </p>
        </div>
      </section>
    </div>

    <div class="flex flex-col gap-6">
      <section class="panel">
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Notifications
          <a href="{{ route('student.notifications') }}" class="ml-auto text-[12px] font-semibold normal-case tracking-normal text-accent-300 no-underline">All →</a>
        </div>
        @forelse ($notifications as $note)
          @include('frontend.student.note', ['note' => $note, 'compact' => true])
        @empty
          <div class="sp-empty">No notifications yet.</div>
        @endforelse
      </section>

      @if ($recommended->isNotEmpty())
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Recommended for you</div>
          @foreach ($recommended as $course)
            <a href="{{ route('courses.show', $course->slug) }}" class="sp-row no-underline hover:bg-muted">
              <span class="grid h-11 w-11 flex-none place-items-center bg-neutral-900 font-heading font-extrabold text-accent">{{ mb_strtoupper(mb_substr($course->title, 0, 1)) }}</span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[14px] font-semibold text-ink">{{ $course->title }}</span>
                <span class="block text-[12px] text-neutral-600">{{ $course->lessons_count }} lessons · {{ $course->level }}</span>
              </span>
              <span class="price !text-[14px] {{ $course->isFree() ? 'price-free' : '' }}">{{ $course->priceLabel() }}</span>
            </a>
          @endforeach
        </section>
      @endif
    </div>
  </div>
@endsection
