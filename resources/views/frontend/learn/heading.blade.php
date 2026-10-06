{{-- The lesson's title row: where it sits, what it is, and "mark complete".
     $dark: on the article band rather than the light page. --}}
<div class="flex flex-wrap items-start gap-x-6 gap-y-4">
  <div class="min-w-0 flex-1">
    <div class="lp-meta mb-2">
      <span class="lp-meta-chip">{{ $lesson->type === 'video' ? '▶ Video' : '≡ Reading' }}</span>
      <span>{{ $lesson->section?->title }}</span>
      <span>Lesson {{ $position }} of {{ $total }}</span>
      @if ($lesson->durationLabel())
        <span>{{ $lesson->durationLabel() }}</span>
      @endif
    </div>
    <h1 class="!mb-0 text-[clamp(22px,3vw,32px)] leading-tight {{ $dark ? 'text-white' : '' }}">{{ $lesson->title }}</h1>
  </div>

  @if ($enrolled)
    {{-- id="lp-complete": the M key and the end of an uploaded video send it. --}}
    <form method="POST" action="{{ route('learn.complete', [$course->slug, $lesson->id]) }}" id="lp-complete" class="flex-none">
      @csrf
      @if ($isDone)
        <input type="hidden" name="undo" value="1">
        <button class="btn lp-done-btn is-done border" title="Click to mark as not complete">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
          Completed
        </button>
      @else
        @if ($next) <input type="hidden" name="next" value="{{ $next->id }}"> @endif
        <button class="btn btn-primary btn-lg">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
          {{ $next ? 'Complete & continue' : 'Complete the course' }}
        </button>
      @endif
    </form>
  @endif
</div>
