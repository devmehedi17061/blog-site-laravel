@extends('frontend.student.shell')

@section('title', $site->title(__('Wishlist')))
@section('panel_title', __('Wishlist'))

@section('panel')
  @if ($courses->isEmpty())
    <div class="panel sp-empty">
      <div class="mb-2 text-[36px]">♡</div>
      {{ __('Tap the ♥ on any course page to save it here for later.') }}
      <div class="mt-3"><a href="{{ route('courses.index') }}" class="btn btn-primary">{{ __('Browse courses') }}</a></div>
    </div>
  @else
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($courses as $course)
        <div class="relative">
          @include('frontend.partials.course-card', ['course' => $course])
          <div class="mt-2 flex gap-2">
            @if (in_array($course->id, $enrolled, true))
              <a href="{{ route('learn.show', $course->slug) }}" class="btn btn-secondary btn-sm flex-1">{{ __('You are enrolled — open') }}</a>
            @else
              <a href="{{ route('courses.show', $course->slug) }}" class="btn btn-primary btn-sm flex-1">{{ $course->isFree() ? __('Enroll for free') : __('Buy').' · '.$course->priceLabel() }}</a>
            @endif
            <form method="POST" action="{{ route('wishlist.toggle', $course->slug) }}">
              @csrf
              <button class="btn btn-ghost btn-sm" title="{{ __('Remove from wishlist') }}">✕ {{ __('Remove') }}</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
