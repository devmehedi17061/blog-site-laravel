{{-- Lesson Q&A: ask, answer, mark answered. --}}
@php $me = auth()->user(); @endphp

@if ($canDiscuss)
  <form method="POST" action="{{ route('discussions.store', [$course->slug, $lesson->id]) }}" class="panel mb-6 flex flex-col gap-3 p-4">
    @csrf
    <label for="qa-ask" class="font-heading text-[14px] font-bold">{{ __('Ask a question about this lesson') }}</label>
    <textarea id="qa-ask" name="body" class="input !min-h-[80px] py-2" maxlength="3000" required
              placeholder="{{ __('Be specific — what did you try, and where did you get stuck?') }}">{{ old('parent_id') ? '' : old('body') }}</textarea>
    @if ($errors->has('body') && ! old('parent_id')) <p class="field-error">{{ $errors->first('body') }}</p> @endif
    <div class="flex items-center gap-3">
      <button class="btn btn-primary">{{ __('Post question') }}</button>
      <span class="text-[12px] text-neutral-500">{{ __('Everyone on the course can see it. You will be notified of replies.') }}</span>
    </div>
  </form>
@else
  <p class="mb-6 text-[14px] text-neutral-600">{{ __('Students on the course can ask questions here.') }}</p>
@endif

@forelse ($discussions as $question)
  <article class="qa-thread" id="qa-{{ $question->id }}">
    <div class="qa-post">
      <span class="qa-avatar">{{ $question->author?->initials ?? '?' }}</span>
      <div class="min-w-0 flex-1">
        <div class="mb-1 flex flex-wrap items-center gap-2 text-[12px] text-neutral-600">
          <strong class="text-[13px] text-ink">{{ $question->author?->name ?? __('Former student') }}</strong>
          @if ($question->byStaff()) <span class="qa-staff">{{ __('Instructor') }}</span> @endif
          <span>{{ $question->created_at->diffForHumans() }}</span>
          @if ($question->is_resolved)
            <span class="tag tag-success !py-0.5">✓ {{ __('Answered') }}</span>
          @else
            <span class="tag tag-accent-2 !py-0.5">{{ __('Open') }}</span>
          @endif
        </div>
        <p class="whitespace-pre-line text-[14px] leading-relaxed text-neutral-800">{{ $question->body }}</p>

        @if ($me && ($question->user_id === $me->id || $me->can('update', $course)))
          <div class="mt-1.5 flex gap-3 text-[12px]">
            <form method="POST" action="{{ route('discussions.resolve', $question) }}">@csrf
              <button class="font-semibold text-neutral-600 hover:text-accent">{{ $question->is_resolved ? __('Mark as open') : __('Mark as answered') }}</button>
            </form>
            <form method="POST" action="{{ route('discussions.destroy', $question) }}">@csrf @method('DELETE')
              <button class="text-neutral-500 hover:text-accent-700">{{ __('Delete') }}</button>
            </form>
          </div>
        @endif
      </div>
    </div>

    @foreach ($question->replies as $reply)
      <div class="qa-post qa-reply {{ $reply->byStaff() ? 'is-staff' : '' }}">
        <span class="qa-avatar">{{ $reply->author?->initials ?? '?' }}</span>
        <div class="min-w-0 flex-1">
          <div class="mb-1 flex flex-wrap items-center gap-2 text-[12px] text-neutral-600">
            <strong class="text-[13px] text-ink">{{ $reply->author?->name ?? __('Former student') }}</strong>
            @if ($reply->byStaff()) <span class="qa-staff">{{ __('Instructor') }}</span> @endif
            <span>{{ $reply->created_at->diffForHumans() }}</span>
            @if ($me && ($reply->user_id === $me->id || $me->can('update', $course)))
              <form method="POST" action="{{ route('discussions.destroy', $reply) }}" class="ml-auto">@csrf @method('DELETE')
                <button class="text-neutral-500 hover:text-accent-700">{{ __('Delete') }}</button>
              </form>
            @endif
          </div>
          <p class="whitespace-pre-line text-[14px] leading-relaxed text-neutral-800">{{ $reply->body }}</p>
        </div>
      </div>
    @endforeach

    @if ($canDiscuss)
      <details class="qa-reply-box" @if ((int) old('parent_id') === $question->id) open @endif>
        <summary>{{ __('Reply') }}</summary>
        <form method="POST" action="{{ route('discussions.store', [$course->slug, $lesson->id]) }}" class="mt-2 flex flex-col gap-2">
          @csrf
          <input type="hidden" name="parent_id" value="{{ $question->id }}">
          <textarea name="body" class="input !min-h-[64px] py-2" maxlength="3000" required placeholder="{{ __('Write a reply…') }}">{{ (int) old('parent_id') === $question->id ? old('body') : '' }}</textarea>
          <div><button class="btn btn-secondary btn-sm">{{ __('Post reply') }}</button></div>
        </form>
      </details>
    @endif
  </article>
@empty
  <div class="sp-empty panel">{{ $canDiscuss ? __('No questions about this lesson yet — be the first to ask.') : __('No questions about this lesson yet.') }}</div>
@endforelse
