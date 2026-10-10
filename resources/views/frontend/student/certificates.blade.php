@extends('frontend.student.shell')

@section('title', $site->title(__('Certificates')))
@section('panel_title', __('Certificates'))

@section('panel')
  @if (! $enabled)
    <div class="panel sp-empty">{{ __('Certificates are not offered on this site.') }}</div>
  @elseif ($enrollments->isEmpty())
    <div class="panel sp-empty">
      <div class="mb-2 text-[40px]">🏆</div>
      {{ __('Finish every lesson of a course and its certificate appears here.') }}
      <div class="mt-3"><a href="{{ route('student.courses') }}" class="btn btn-primary">{{ __('Go to my courses') }}</a></div>
    </div>
  @else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($enrollments as $enrollment)
        <article class="panel flex flex-col">
          <div class="relative flex aspect-[1.414/1] flex-col items-center justify-center gap-1 border-b border-line bg-surface p-5 text-center"
               style="box-shadow: inset 0 0 0 8px #fff, inset 0 0 0 9px var(--color-accent)">
            <div class="text-[9px] font-bold tracking-[0.25em] text-neutral-500 uppercase">{{ __('Certificate of completion') }}</div>
            <div class="font-heading text-[18px] font-extrabold">{{ $enrollment->student?->name ?? auth()->user()->name }}</div>
            <div class="text-[12px] font-semibold text-accent">{{ $enrollment->course->title }}</div>
          </div>
          <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="text-[13px] text-neutral-600">{{ __('Completed :date', ['date' => $site->date($enrollment->completed_at)]) }}</div>
            <div class="mt-auto flex gap-2">
              <a href="{{ route('student.certificate', $enrollment) }}" class="btn btn-primary flex-1">{{ __('View & print') }}</a>
              <a href="{{ route('courses.show', $enrollment->course->slug) }}" class="btn btn-secondary">{{ __('Course') }}</a>
            </div>
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection
