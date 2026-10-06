{{-- The one-line message a redirect carries ("You are enrolled", "Saved"). --}}
@if (session('status'))
  <div class="flash {{ $class ?? 'mb-6' }}" role="status">{{ session('status') }}</div>
@endif
