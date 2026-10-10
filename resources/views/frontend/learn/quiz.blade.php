{{--
  A quiz lesson: the result of the try just made, a review of it, and the
  questions. Right answers are only shown once they cannot help guessing —
  after a pass, or with no tries left ($quiz['reveal']).
--}}
@php
  $questions = $quiz['questions'];
  $result = $quiz['result'] ?? $quiz['attempts']->first();
  $justTook = (bool) $quiz['result'];
  $preview = session('quiz_preview');
  $canTake = ($enrollment?->isActive() && ($quiz['left'] === null || $quiz['left'] > 0)) || (! $enrollment?->isActive() && $hasAccess);
  $possible = $questions->sum('points');
  $letters = range('A', 'J');
  $triesLeft = fn ($n) => trans_choice(':count try left|:count tries left', $n, ['count' => $n]);
@endphp

<div class="quiz mt-6">
  {{-- the quiz at a glance --}}
  <div class="quiz-facts">
    <div><strong>{{ $questions->count() }}</strong><span>{{ __('questions') }}</span></div>
    <div><strong>{{ $lesson->pass_mark }}%</strong><span>{{ __('to pass') }}</span></div>
    <div><strong>{{ $lesson->max_attempts ?: '∞' }}</strong><span>{{ trans_choice('try|tries', $lesson->max_attempts === 1 ? 1 : 2) }}</span></div>
    @if ($quiz['attempts']->isNotEmpty())
      <div><strong>{{ $quiz['best'] }}%</strong><span>{{ __('your best') }}</span></div>
    @endif
  </div>

  @if (session('quiz_error'))
    <div class="pay-alert is-rejected mt-4">{{ session('quiz_error') }}</div>
  @endif

  {{-- staff trying the quiz: graded, not recorded --}}
  @if ($preview)
    <div class="quiz-result {{ $preview['passed'] ? 'is-pass' : 'is-fail' }} mt-5">
      <div class="quiz-score">{{ $preview['score'] }}%</div>
      <div><strong>{{ $preview['passed'] ? __('Preview — that would pass.') : __('Preview — that would not pass.') }}</strong>
        <span>{{ __('You can edit this course, so the try was graded but not saved.') }}</span></div>
    </div>
  @endif

  {{-- the try just submitted --}}
  @if ($justTook && $result)
    <div class="quiz-result {{ $result->passed ? 'is-pass' : 'is-fail' }} mt-5" role="status">
      <div class="quiz-score">{{ $result->score }}%</div>
      <div>
        @if ($result->passed)
          <strong>{{ __('Passed — well done!') }} 🎉</strong>
          <span>
            {{ __(':earned of :possible points.', ['earned' => $result->earned, 'possible' => $result->possible]) }}
            {{ $next ? __('The lesson is complete and the next one is open.') : __('The lesson is complete.') }}
          </span>
        @else
          <strong>{{ __('Not quite — :n% is needed to pass.', ['n' => $lesson->pass_mark]) }}</strong>
          <span>{{ __(':earned of :possible points.', ['earned' => $result->earned, 'possible' => $result->possible]) }}
            @if ($quiz['left'] === 0) {{ __('No tries left — the right answers are shown below.') }}
            @elseif ($quiz['left'] !== null) {{ $triesLeft($quiz['left']) }}.
            @else {{ __('Look at what was wrong below, then try again.') }}
            @endif
          </span>
        @endif
      </div>
      @if ($result->passed && $next)
        <a href="{{ route('learn.show', [$course->slug, $next->id]) }}" class="btn btn-primary ml-auto">{{ __('Next lesson') }} →</a>
      @endif
    </div>
  @elseif ($quiz['passed'] && ! $justTook)
    <div class="quiz-result is-pass mt-5">
      <div class="quiz-score">{{ $quiz['best'] }}%</div>
      <div><strong>{{ __('You passed this quiz.') }}</strong>
        <span>{{ $canTake ? __('Your answers from the last try are below; you can take it again to improve your score.') : __('Your answers from the last try are below.') }}</span></div>
    </div>
  @endif

  {{-- review: what was chosen, and (when revealed) what was right --}}
  @if ($result && ($justTook || $quiz['reveal']))
    <div class="mt-6 flex flex-col gap-3">
      <h3 class="!mb-0 text-[17px]">{{ __('Your answers') }}</h3>
      @foreach ($questions as $n => $question)
        @php
          $picked = array_map('intval', (array) ($result->answers[$question->id] ?? []));
          $right = $question->isRight($picked);
          $correct = $question->correctIndexes();
        @endphp
        <div class="quiz-q {{ $right ? 'is-right' : 'is-wrong' }}">
          <div class="quiz-q-head">
            <span class="quiz-q-mark">{{ $right ? '✓' : '✕' }}</span>
            <span class="flex-1">{{ $n + 1 }}. {{ $question->question }}</span>
          </div>
          <ul class="quiz-options">
            @foreach ($question->options as $i => $option)
              @php
                $mine = in_array($i, $picked, true);
                $isCorrect = in_array($i, $correct, true);
              @endphp
              <li class="{{ $mine ? 'is-mine' : '' }} {{ $quiz['reveal'] && $isCorrect ? 'is-correct' : '' }} {{ $mine && ! $isCorrect && $quiz['reveal'] ? 'is-wrong' : '' }}">
                <span class="quiz-letter">{{ $letters[$i] ?? $i + 1 }}</span>
                <span class="flex-1">{{ __($option['text']) }}</span>
                @if ($mine) <span class="text-[11px] font-bold">{{ __('Your answer') }}</span> @endif
                @if ($quiz['reveal'] && $isCorrect) <span class="text-[11px] font-bold text-success-800">{{ __('Right answer') }}</span> @endif
              </li>
            @endforeach
          </ul>
          @if ($quiz['reveal'] && $question->explanation)
            <p class="quiz-explain"><strong>{{ __('Why:') }}</strong> {{ $question->explanation }}</p>
          @endif
        </div>
      @endforeach
    </div>
  @endif

  {{-- the questions --}}
  @if ($questions->isEmpty())
    <p class="mt-5 text-neutral-600">{{ __('This quiz has no questions yet.') }}</p>
  @elseif ($canTake)
    <details class="mt-6" @if (! $quiz['passed'] && ! ($justTook && $result)) open @endif>
      <summary class="btn {{ $quiz['passed'] || ($justTook && $result) ? 'btn-secondary' : 'hidden' }} cursor-pointer list-none">
        {{ $quiz['passed'] ? __('Take the quiz again') : __('Try again') }}
      </summary>

      <form method="POST" action="{{ route('learn.quiz', [$course->slug, $lesson->id]) }}" class="mt-4 flex flex-col gap-4" id="quiz-form">
        @csrf
        @foreach ($questions as $n => $question)
          <fieldset class="quiz-q">
            <legend class="quiz-q-head">
              <span class="quiz-q-num">{{ $n + 1 }}</span>
              <span class="flex-1">{{ $question->question }}</span>
              <span class="text-[11px] font-normal text-neutral-500">
                {{ $question->type === 'multiple' ? __('Choose all that apply') : __('Choose one') }}
                @if ($question->points > 1) · {{ trans_choice(':count point|:count points', $question->points, ['count' => $question->points]) }} @endif
              </span>
            </legend>
            <div class="quiz-options">
              @foreach ($question->options as $i => $option)
                <label class="quiz-choice">
                  <input type="{{ $question->type === 'multiple' ? 'checkbox' : 'radio' }}"
                         name="answers[{{ $question->id }}][]" value="{{ $i }}"
                         @checked(in_array((string) $i, (array) old('answers.'.$question->id, []), true))>
                  <span class="quiz-letter">{{ $letters[$i] ?? $i + 1 }}</span>
                  {{-- "True" / "False" follow the language; other answers show as written. --}}
                  <span>{{ __($option['text']) }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>
        @endforeach

        <div class="flex flex-wrap items-center gap-3 border-t border-line-strong pt-4">
          <button class="btn btn-primary btn-lg">{{ __('Submit answers') }}</button>
          <span class="text-[13px] text-neutral-600">
            {{ trans_choice(':count point|:count points', $possible, ['count' => $possible]) }} · {{ __(':n% to pass', ['n' => $lesson->pass_mark]) }}
            @if ($quiz['left'] !== null) · {{ $triesLeft($quiz['left']) }} @endif
          </span>
        </div>
      </form>
    </details>
  @elseif ($enrollment?->isActive())
    <p class="pay-alert is-rejected mt-5">{{ __('You have used all your tries on this quiz. Contact your instructor if you need another.') }}</p>
  @else
    <div class="panel mt-5 p-5 text-[14px] text-neutral-700">
      {{ __('Enroll in the course to take this quiz.') }}
      <a href="{{ route('courses.show', $course->slug) }}" class="btn btn-primary btn-sm ml-2">{{ __('See the course') }}</a>
    </div>
  @endif
</div>
