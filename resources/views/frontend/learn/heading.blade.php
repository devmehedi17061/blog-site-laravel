{{-- The lesson's title row: where it sits, what it is, and "mark complete".
     $dark: on the article band rather than the light page. --}}
<div class="flex flex-wrap items-start gap-x-6 gap-y-4">
  <div class="min-w-0 flex-1">
    <div class="lp-meta mb-2">
      <span class="lp-meta-chip">{{ ['video' => '▶ ', 'quiz' => '? '][$lesson->type] ?? '≡ ' }}{{ __(['video' => 'Video', 'quiz' => 'Quiz'][$lesson->type] ?? 'Reading') }}</span>
      <span>{{ $lesson->section?->title }}</span>
      <span>{{ __('Lesson :n of :total', ['n' => $position, 'total' => $total]) }}</span>
      @if ($lesson->durationLabel())
        <span>{{ $lesson->durationLabel() }}</span>
      @endif
    </div>
    <h1 class="!mb-0 text-[clamp(22px,3vw,32px)] leading-tight {{ $dark ? 'text-white' : '' }}">{{ $lesson->title }}</h1>
  </div>

  @if ($enrolled && $lesson->isQuiz())
    {{-- a quiz completes itself, when it is passed --}}
    <div class="flex-none">
      @if ($quiz['passed'])
        <span class="btn lp-done-btn is-done border">✓ {{ __('Passed · best :n%', ['n' => $quiz['best']]) }}</span>
      @else
        <span class="tag tag-accent-2 !py-2 !text-[11px]">{{ __('Pass mark :n%', ['n' => $lesson->pass_mark]) }}</span>
      @endif
    </div>
  @elseif ($enrolled)
    {{-- id="lp-complete": the M key and the end of an uploaded video send it. --}}
    <form method="POST" action="{{ route('learn.complete', [$course->slug, $lesson->id]) }}" id="lp-complete" class="flex-none">
      @csrf
      @if ($isDone)
        <input type="hidden" name="undo" value="1">
        <button class="btn lp-done-btn is-done border" title="{{ __('Click to mark as not complete') }}">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
          {{ __('Completed') }}
        </button>
      @else
        @if ($next) <input type="hidden" name="next" value="{{ $next->id }}"> @endif
        <button class="btn btn-primary btn-lg">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
          {{ $next ? __('Complete & continue') : __('Complete the course') }}
        </button>
      @endif
    </form>
  @endif
</div>
