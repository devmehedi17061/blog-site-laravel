@extends('frontend.student.shell')

@section('title', $site->title(__('My courses')))
@section('panel_title', __('My courses'))

@section('panel')
  @php
    $tabs = ['progress' => 'In progress', 'completed' => 'Completed', 'pending' => 'Awaiting payment'];
  @endphp
  <nav class="sp-tabs" aria-label="{{ __('Courses') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('student.courses', ['tab' => $key]) }}" class="sp-tab {{ $tab === $key ? 'is-on' : '' }}">{{ __($label) }}<b>{{ $groups[$key]->count() }}</b></a>
    @endforeach
  </nav>

  @if ($groups[$tab]->isEmpty())
    <div class="panel sp-empty">
      {{ __(['progress' => 'No courses in progress.', 'completed' => 'No finished courses yet — keep going!', 'pending' => 'No payments waiting.'][$tab]) }}
      <div class="mt-3"><a href="{{ route('courses.index') }}" class="btn btn-primary">{{ __('Browse courses') }}</a></div>
    </div>
  @else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($groups[$tab] as $enrollment)
        @php $c = $enrollment->course; $pct = $progress[$enrollment->id] ?? 0; @endphp
        <article class="panel course-card">
          <a href="{{ $tab === 'pending' ? route('courses.show', $c->slug) : route('learn.show', $c->slug) }}" class="course-card-thumb" tabindex="-1" aria-hidden="true">
            @if ($c->thumbnail)
              <img src="{{ $c->thumbnail->url }}" alt="">
            @else
              <span class="course-card-thumb-blank">{{ mb_strtoupper(mb_substr($c->title, 0, 1)) }}</span>
            @endif
            @if ($tab === 'completed') <span class="course-card-badge !bg-success-800">✓ {{ __('Completed') }}</span> @endif
            @if ($tab === 'pending') <span class="course-card-badge !bg-warning-800">{{ __('Verifying payment') }}</span> @endif
          </a>
          <div class="flex flex-1 flex-col gap-3 p-5">
            <h3 class="!mb-0 flex-1 text-[17px] leading-snug">{{ $c->title }}</h3>
            <div class="text-[12px] text-neutral-600">
              {{ $c->instructor?->name }} · {{ trans_choice(':count lesson|:count lessons', $c->lessons_count, ['count' => $c->lessons_count]) }}
              @if ($enrollment->last_activity_at) · {{ __('last opened :when', ['when' => $enrollment->last_activity_at->diffForHumans()]) }} @endif
            </div>
            @if ($tab !== 'pending')
              <div class="flex items-center gap-3">
                <div class="progress {{ $pct >= 100 ? 'progress-done' : '' }} flex-1"><span style="width: {{ $pct }}%"></span></div>
                <strong class="text-[12px]">{{ $pct }}%</strong>
              </div>
            @endif
            <div class="flex gap-2">
              @if ($tab === 'pending')
                <a href="{{ route('student.payments') }}" class="btn btn-secondary w-full">{{ __('See payment status') }}</a>
              @elseif ($tab === 'completed')
                <a href="{{ route('learn.show', $c->slug) }}" class="btn btn-secondary flex-1">{{ __('Review') }}</a>
                @if (\App\Support\Lms::setting('certificates'))
                  <a href="{{ route('student.certificate', $enrollment) }}" class="btn btn-primary flex-1">{{ __('Certificate') }}</a>
                @endif
              @else
                <a href="{{ $enrollment->last_lesson_id ? route('learn.show', [$c->slug, $enrollment->last_lesson_id]) : route('learn.show', $c->slug) }}" class="btn btn-primary w-full">
                  {{ $pct > 0 ? __('Continue') : __('Start') }} →
                </a>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection
