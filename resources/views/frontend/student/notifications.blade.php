@extends('frontend.student.shell')

@section('title', $site->title(__('Notifications')))
@section('panel_title', __('Notifications'))
@section('panel_actions')
  @if (($panelCounts['notifications'] ?? 0) > 0)
    <form method="POST" action="{{ route('student.notifications.read') }}">
      @csrf
      <button class="btn btn-secondary">{{ __('Mark all as read') }}</button>
    </form>
  @endif
@endsection

@section('panel')
  <div class="panel">
    @forelse ($notifications as $note)
      @include('frontend.student.note', ['note' => $note])
    @empty
      <div class="sp-empty">
        <div class="mb-2 text-[36px]">🔔</div>
        {{ __('Nothing yet. Payment checks and course news show up here.') }}
      </div>
    @endforelse
  </div>
  <div class="mt-6">{{ $notifications->links() }}</div>
@endsection
