@extends('frontend.layout')

@section('title', $site->title($lesson->title.' — '.$course->title))
{{-- Lessons are behind a sign-in; nothing here is for search engines. --}}
@section('robots', 'noindex, nofollow')
@section('main_class', 'w-full')

@php
  $enrolled = (bool) $enrollment?->isActive();
  $isDone = in_array($lesson->id, $done, true);
  $hoursLeft = $minutesLeft >= 60 ? intdiv($minutesLeft, 60).'h '.($minutesLeft % 60).'m' : $minutesLeft.' min';
  // Lessons in order: the next one stays shut until this one is complete.
  $nextLocked = $next && in_array($next->id, $locked, true);
  $nextOpen = $next && ($hasAccess || $next->is_preview) && ! $nextLocked;
  $prevUrl = $previous ? route('learn.show', [$course->slug, $previous->id]) : null;
  $nextUrl = $nextOpen ? route('learn.show', [$course->slug, $next->id]) : null;
  $ring = 2 * M_PI * 16;
  $icon = [
    'video' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 4.5v15l13-7.5Z"/></svg>',
    'article' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 7h12M6 12h12M6 17h7"/></svg>',
  ];
@endphp

@section('content')
<div class="lp" id="lp" style="--lp-progress: {{ $enrolled ? $progress : 0 }}%"
     data-lesson="{{ $lesson->id }}" data-prev="{{ $prevUrl }}" data-next="{{ $nextUrl }}">

  {{-- ── the bar ────────────────────────────────────────────────────── --}}
  <div class="lp-bar">
    <a href="{{ route('courses.show', $course->slug) }}" class="lp-back" title="Back to the course page">
      <span class="lp-back-icon" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m15 18-6-6 6-6"/></svg>
      </span>
      <span class="lp-back-title">{{ $course->title }}</span>
    </a>

    <div class="ml-auto flex items-center gap-3">
      @if ($enrolled)
        <div class="hidden items-center gap-2.5 sm:flex" title="{{ $doneCount }} of {{ $total }} lessons complete">
          <div class="lp-ring {{ $progress >= 100 ? 'is-done' : '' }}">
            <svg width="38" height="38" viewBox="0 0 38 38" aria-hidden="true">
              <circle class="lp-ring-track" cx="19" cy="19" r="16" fill="none" stroke-width="3"/>
              <circle class="lp-ring-fill" cx="19" cy="19" r="16" fill="none" stroke-width="3"
                      stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring * (1 - $progress / 100) }}"/>
            </svg>
            <span class="lp-ring-label">{{ $progress }}%</span>
          </div>
          <div class="lp-bar-stat">
            <strong>{{ $doneCount }}/{{ $total }} lessons</strong>
            <span>{{ $progress >= 100 ? 'Course complete' : $hoursLeft.' left' }}</span>
          </div>
        </div>
        @if ($enrollment->completed_at && \App\Support\Lms::setting('certificates'))
          <a href="{{ route('student.certificate', $enrollment) }}" class="btn btn-primary btn-sm hidden md:inline-flex">🏆 Certificate</a>
        @endif
      @elseif (! $hasAccess)
        <span class="tag tag-accent">Free preview</span>
        <a href="{{ route('courses.show', $course->slug) }}" class="btn btn-primary btn-sm">Enroll</a>
      @endif

      <span class="lp-bar-sep hidden sm:block"></span>
      <a href="{{ $prevUrl ?? '#' }}" class="lp-icon-btn {{ $prevUrl ? '' : 'is-disabled' }}" title="Previous lesson (P)" aria-label="Previous lesson">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m15 18-6-6 6-6"/></svg>
      </a>
      <a href="{{ $nextUrl ?? '#' }}" class="lp-icon-btn {{ $nextUrl ? '' : 'is-disabled' }}"
         title="{{ $nextLocked ? 'Complete this lesson to unlock the next one' : 'Next lesson (N)' }}" aria-label="Next lesson">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m9 18 6-6-6-6"/></svg>
      </a>
      <button type="button" class="lp-icon-btn hidden lg:inline-grid" data-focus-toggle title="Focus mode — hide the course content (F)" aria-label="Toggle course content">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16"/><path d="M15 4v16"/></svg>
      </button>
    </div>
  </div>

  <div class="lp-body">
    <div class="lp-main">

      {{-- ── the stage ────────────────────────────────────────────────── --}}
      @if ($lesson->type === 'video' && ($lesson->hasUpload() || $lesson->video_url))
        <div class="lp-stage">
          <div class="lp-stage-inner">
            @if ($lesson->hasUpload())
              {{-- Streamed through the site, never a public file. --}}
              <video id="lp-video" src="{{ route('learn.video', [$course->slug, $lesson->id]) }}" controls preload="metadata"
                     controlsList="nodownload" disablepictureinpicture oncontextmenu="return false" playsinline></video>
              <div class="lp-next" id="lp-next" hidden>
                <div>
                  {{-- Finishing the video completes the lesson, which unlocks the next. --}}
                  @if ($next && ($hasAccess || $next->is_preview))
                    <div class="lp-next-count" id="lp-next-count">5</div>
                    <div class="text-[12px] font-bold tracking-[0.14em] text-accent-300 uppercase">Up next</div>
                    <div class="mt-1 mb-5 font-heading text-[20px] font-bold">{{ $next->title }}</div>
                    <div class="flex justify-center gap-2">
                      <button type="button" class="btn btn-secondary" id="lp-next-cancel">Stay here</button>
                      <button type="button" class="btn btn-primary" id="lp-next-go">Play now →</button>
                    </div>
                  @else
                    <div class="font-heading text-[22px] font-bold">That was the last lesson 🎉</div>
                    <button type="button" class="btn btn-secondary mt-4" id="lp-next-cancel">Watch again</button>
                  @endif
                </div>
              </div>
            @elseif ($embed = $lesson->embedUrl())
              <iframe src="{{ $embed }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            @else
              <video src="{{ $lesson->video_url }}" controls controlsList="nodownload" preload="metadata" playsinline></video>
            @endif
          </div>
        </div>
      @endif

      {{-- ── the lesson ───────────────────────────────────────────────── --}}
      @if ($lesson->type === 'article' || ! ($lesson->hasUpload() || $lesson->video_url))
        <div class="lp-article-band">
          <div class="mx-auto max-w-[920px] px-6">
            @include('frontend.learn.heading', ['dark' => true])
          </div>
        </div>
      @endif

      <div class="lp-content">
        @if ($lesson->type === 'video' && ($lesson->hasUpload() || $lesson->video_url))
          @include('frontend.learn.heading', ['dark' => false])
        @endif

        @if (session('celebrate'))
          <div class="mt-5 flex flex-wrap items-center gap-3 bg-success-100 px-5 py-4 text-success-800">
            <strong class="font-heading text-[16px]">🎉 {{ session('status') }}</strong>
            @if (\App\Support\Lms::setting('certificates') && $enrollment)
              <a href="{{ route('student.certificate', $enrollment) }}" class="btn btn-primary btn-sm ml-auto">Get your certificate</a>
            @endif
          </div>
        @else
          @include('frontend.partials.flash', ['class' => 'mt-5'])
        @endif

        {{-- tabs: the lesson, its files, the student's own notes --}}
        <div class="lp-tabs" role="tablist">
          <button type="button" class="lp-tab" role="tab" aria-selected="true" data-tab="overview">Overview</button>
          @if ($lesson->attachment)
            <button type="button" class="lp-tab" role="tab" aria-selected="false" data-tab="resources">Resources<span class="lp-tab-count">1</span></button>
          @endif
          <button type="button" class="lp-tab" role="tab" aria-selected="false" data-tab="notes">My notes</button>
        </div>

        <div class="lp-panel" data-panel="overview">
          @if ($lesson->body)
            <div class="post-body rich-content">{!! $lesson->body !!}</div>
          @else
            <p class="text-[15px] text-neutral-600">
              {{ $lesson->type === 'video' ? 'Watch the video above.' : 'This lesson has no text yet.' }}
            </p>
          @endif
        </div>

        @if ($lesson->attachment)
          <div class="lp-panel" data-panel="resources" hidden>
            <a href="{{ $lesson->attachment->url }}" download class="panel flex items-center gap-4 p-4 no-underline transition hover:border-accent hover:shadow-md">
              <span class="grid h-12 w-12 flex-none place-items-center bg-accent text-white">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[15px] font-semibold text-ink">{{ $lesson->attachment->name }}</span>
                <span class="block text-[12px] text-neutral-600">{{ strtoupper(pathinfo($lesson->attachment->name, PATHINFO_EXTENSION)) }} · {{ $lesson->attachment->human_size }}</span>
              </span>
              <span class="btn btn-secondary btn-sm">Download</span>
            </a>
          </div>
        @endif

        <div class="lp-panel" data-panel="notes" hidden>
          <textarea class="lp-notes" id="lp-notes" placeholder="Jot down what you want to remember from this lesson…"></textarea>
          <p class="mt-2 text-[12px] text-neutral-500"><span id="lp-notes-state">Saved automatically</span> — kept in this browser, only you can see them.</p>
        </div>

        {{-- previous / next --}}
        @if ($previous || $next)
          <nav class="lp-nav" aria-label="Lessons">
            @if ($previous)
              <a href="{{ $prevUrl }}" class="lp-nav-card"><span>← Previous</span><strong>{{ $previous->title }}</strong></a>
            @endif
            @if ($next)
              @if ($nextOpen)
                <a href="{{ $nextUrl }}" class="lp-nav-card is-next"><span>Next →</span><strong>{{ $next->title }}</strong></a>
              @elseif ($nextLocked)
                <div class="lp-nav-card is-next is-locked" title="Lessons open in order">
                  <span>🔒 Next — complete this lesson to unlock</span><strong>{{ $next->title }}</strong>
                </div>
              @else
                <a href="{{ route('courses.show', $course->slug) }}" class="lp-nav-card is-next"><span>🔒 Next — enroll to continue</span><strong>{{ $next->title }}</strong></a>
              @endif
            @endif
          </nav>
        @endif

        <div class="lp-keys hidden lg:flex" aria-hidden="true">
          <span><kbd>N</kbd>next</span>
          <span><kbd>P</kbd>previous</span>
          @if ($enrolled)<span><kbd>M</kbd>mark complete</span>@endif
          <span><kbd>F</kbd>focus mode</span>
        </div>
      </div>
    </div>

    {{-- ── the side panel ─────────────────────────────────────────────── --}}
    <aside class="lp-side" aria-label="Course content">
      <div class="lp-side-head">
        <div class="kicker">Course content</div>
        @if ($enrolled)
          <div class="lp-side-progress"><span style="width: {{ $progress }}%"></span></div>
          <div class="flex justify-between text-[12px] text-neutral-600">
            <span><strong class="text-ink">{{ $doneCount }}</strong> of {{ $total }} complete</span>
            <span>{{ $progress >= 100 ? 'Done 🎉' : $hoursLeft.' left' }}</span>
          </div>
        @else
          <div class="mt-1 text-[12px] text-neutral-600">{{ $total }} lessons · {{ $course->sections->count() }} sections</div>
        @endif
        <input type="search" class="lp-search" id="lp-search" placeholder="Find a lesson…" aria-label="Find a lesson">
      </div>

      <div class="lp-side-list" id="lp-list">
        @foreach ($course->sections as $section)
          @php
            $sectionDone = $section->lessons->filter(fn ($l) => in_array($l->id, $done, true))->count();
            $sectionTotal = $section->lessons->count();
          @endphp
          <details class="lp-section" @if ($section->lessons->contains('id', $lesson->id) || ($loop->first && ! $course->sections->flatMap->lessons->contains('id', $lesson->id))) open @endif>
            <summary>
              <span class="lp-section-top">
                <span class="min-w-0">{{ $section->title }}</span>
                <span class="lp-section-count">{{ $sectionDone }}/{{ $sectionTotal }}</span>
              </span>
              @if ($enrolled)
                <span class="lp-section-bar"><span style="width: {{ $sectionTotal ? round($sectionDone / $sectionTotal * 100) : 0 }}%"></span></span>
              @endif
            </summary>

            @foreach ($section->lessons as $item)
              @php
                $waiting = in_array($item->id, $locked, true);
                $open = ($hasAccess || $item->is_preview) && ! $waiting;
                $itemDone = in_array($item->id, $done, true);
                $current = $item->id === $lesson->id;
              @endphp
              <{{ $open ? 'a' : 'div' }} @if ($open) href="{{ route('learn.show', [$course->slug, $item->id]) }}" @endif
                 class="lp-item {{ $current ? 'is-current' : '' }} {{ $itemDone ? 'is-done' : '' }} {{ $open ? '' : 'is-locked' }}"
                 data-title="{{ mb_strtolower($item->title) }}" @if ($current) aria-current="page" @endif>
                <span class="lp-item-status" aria-label="{{ $itemDone ? 'Complete' : ($open ? 'Not complete' : 'Locked') }}"
                      @if ($waiting) title="Opens when the lessons before it are complete" @endif>
                  @if ($itemDone)
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><path d="M5 12l5 5L20 7"/></svg>
                  @elseif ($current)
                    {!! $icon[$item->type] ?? $icon['video'] !!}
                  @elseif (! $open)
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                  @endif
                </span>
                <span class="lp-item-text">
                  <span class="lp-item-title">{{ $item->title }}</span>
                  <span class="lp-item-meta">
                    {!! $icon[$item->type] ?? '' !!}
                    {{ $item->type === 'video' ? 'Video' : 'Reading' }}
                    @if ($item->durationLabel()) · {{ $item->durationLabel() }} @endif
                    @if ($item->is_preview && ! $hasAccess) · <span class="is-free">Free</span> @endif
                  </span>
                </span>
              </{{ $open ? 'a' : 'div' }}>
            @endforeach
          </details>
        @endforeach
        <p class="lp-empty-search" id="lp-empty" hidden>No lesson matches.</p>
      </div>
    </aside>
  </div>
</div>

{{-- The player's small helpers. Nothing here is needed to learn: without
     JavaScript the page still plays, links and marks lessons complete. --}}
<script>
(() => {
  const root = document.getElementById('lp')
  if (!root) return
  const lessonId = root.dataset.lesson

  // The site header is pinned and its height depends on the design chosen
  // under Header & footer: the side panel and the video size follow it.
  const fit = () => {
    const top = document.querySelector('.site-top:not(.is-static)')
    root.style.setProperty('--lp-top', (top ? top.offsetHeight : 0) + 'px')
  }
  fit()
  addEventListener('resize', fit)
  const store = {
    get: (k) => { try { return localStorage.getItem(k) } catch { return null } },
    set: (k, v) => { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v) } catch {} },
  }

  // Focus mode: the course content folds away, remembered for next time.
  const focusBtn = root.querySelector('[data-focus-toggle]')
  const setFocus = (on) => { root.classList.toggle('is-focus', on); focusBtn?.classList.toggle('is-on', on); store.set('lp-focus', on ? '1' : null) }
  setFocus(store.get('lp-focus') === '1')
  focusBtn?.addEventListener('click', () => setFocus(!root.classList.contains('is-focus')))

  // Tabs.
  root.querySelectorAll('.lp-tab').forEach((tab) => tab.addEventListener('click', () => {
    root.querySelectorAll('.lp-tab').forEach((t) => t.setAttribute('aria-selected', String(t === tab)))
    root.querySelectorAll('.lp-panel').forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.tab })
    if (tab.dataset.tab === 'notes') document.getElementById('lp-notes')?.focus()
  }))

  // My notes, per lesson, in this browser.
  const notes = document.getElementById('lp-notes')
  const notesState = document.getElementById('lp-notes-state')
  if (notes) {
    notes.value = store.get('lp-notes:' + lessonId) ?? ''
    let timer
    notes.addEventListener('input', () => {
      notesState.textContent = 'Saving…'
      clearTimeout(timer)
      timer = setTimeout(() => { store.set('lp-notes:' + lessonId, notes.value || null); notesState.textContent = 'Saved' }, 400)
    })
  }

  // Find a lesson.
  const search = document.getElementById('lp-search')
  search?.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase()
    let shown = 0
    root.querySelectorAll('.lp-section').forEach((section) => {
      let any = false
      section.querySelectorAll('.lp-item').forEach((item) => {
        const hit = !term || item.dataset.title.includes(term)
        item.hidden = !hit
        any ||= hit
        shown += hit ? 1 : 0
      })
      section.hidden = !any
      if (term && any) section.open = true
    })
    document.getElementById('lp-empty').hidden = shown > 0
  })

  // The current lesson in view in the side panel — scrolling the panel's
  // own list only. On a phone the list sits under the lesson and does not
  // scroll by itself; moving the page there would hide the video.
  const list = document.getElementById('lp-list')
  const current = root.querySelector('.lp-item.is-current')
  if (list && current && list.scrollHeight > list.clientHeight) {
    list.scrollTop = current.offsetTop - list.offsetTop - list.clientHeight / 2
  }

  // Keyboard: N next, P previous, M mark complete, F focus mode.
  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable) return
    const key = e.key.toLowerCase()
    if (key === 'n' && root.dataset.next) location.href = root.dataset.next
    else if (key === 'p' && root.dataset.prev) location.href = root.dataset.prev
    else if (key === 'm') document.getElementById('lp-complete')?.requestSubmit()
    else if (key === 'f' && focusBtn) setFocus(!root.classList.contains('is-focus'))
  })

  // An uploaded video: carry on where it was left, and roll on to the next lesson.
  const video = document.getElementById('lp-video')
  if (video) {
    const key = 'lp-time:' + lessonId
    video.addEventListener('loadedmetadata', () => {
      const t = parseFloat(store.get(key) || '0')
      if (t > 5 && t < video.duration - 10) video.currentTime = t
    })
    let last = 0
    video.addEventListener('timeupdate', () => {
      if (Math.abs(video.currentTime - last) > 4) { last = video.currentTime; store.set(key, String(Math.floor(last))) }
    })

    const overlay = document.getElementById('lp-next')
    const count = document.getElementById('lp-next-count')
    let tick
    const goNext = () => {
      const form = document.getElementById('lp-complete')
      // Finishing the video marks the lesson complete, then moves on.
      if (form && !form.querySelector('[name=undo]')) form.requestSubmit()
      else if (root.dataset.next) location.href = root.dataset.next
    }
    video.addEventListener('ended', () => {
      store.set(key, null)
      overlay.hidden = false
      if (!count) return
      let n = 5
      count.textContent = n
      tick = setInterval(() => { n -= 1; count.textContent = n; if (n <= 0) { clearInterval(tick); goNext() } }, 1000)
    })
    document.getElementById('lp-next-go')?.addEventListener('click', () => { clearInterval(tick); goNext() })
    document.getElementById('lp-next-cancel')?.addEventListener('click', () => { clearInterval(tick); overlay.hidden = true; video.currentTime = 0 })
  }
})()
</script>
@endsection
