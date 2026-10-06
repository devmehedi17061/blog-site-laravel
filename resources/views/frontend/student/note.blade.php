{{-- One notification. $compact: the short form on the overview. --}}
@php
  $data = $note->data;
  $tone = $data['tone'] ?? 'info';
@endphp
<a href="{{ route('student.notifications.open', $note->id) }}" class="sp-note {{ $note->read_at ? '' : 'is-unread' }}">
  <span class="sp-note-icon is-{{ $tone }}" aria-hidden="true">
    @if ($tone === 'success')
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
    @elseif ($tone === 'warn')
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>
    @else
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/></svg>
    @endif
  </span>
  <span class="min-w-0 flex-1">
    <span class="block text-[14px] {{ $note->read_at ? 'font-medium' : 'font-bold' }}">{{ $data['title'] ?? 'Notification' }}</span>
    @unless ($compact ?? false)
      <span class="mt-0.5 block text-[13px] leading-relaxed text-neutral-700">{{ $data['body'] ?? '' }}</span>
    @endunless
    <span class="mt-1 block text-[11px] text-neutral-500">{{ $note->created_at->diffForHumans() }}</span>
  </span>
  @if (! empty($data['action']) && ! ($compact ?? false))
    <span class="btn btn-secondary btn-sm self-center">{{ $data['action'] }}</span>
  @endif
</a>
