@extends('frontend.layout')

@section('title', $site->title('Checkout — '.$course->title))
@section('robots', 'noindex, nofollow')

@section('content')

  <nav class="mb-6 text-[13px] text-neutral-600" aria-label="Breadcrumb">
    <a href="{{ route('courses.index') }}" class="text-accent no-underline">Courses</a>
    <span class="mx-2">/</span>
    <a href="{{ route('courses.show', $course->slug) }}" class="text-accent no-underline">{{ $course->title }}</a>
    <span class="mx-2">/</span>
    <span>Checkout</span>
  </nav>

  {{-- the three steps, so the student knows what happens after the form --}}
  <ol class="checkout-steps mb-8">
    <li class="is-on"><span>1</span> Send the fee</li>
    <li class="is-on"><span>2</span> Tell us the transaction</li>
    <li><span>3</span> We verify — the course opens</li>
  </ol>

  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">

    <div class="flex flex-col gap-6">
      @if ($rejected)
        <div class="pay-alert is-rejected">
          <strong>Your last payment could not be verified.</strong>
          <span>Reason: {{ $rejected->rejection_reason }}</span>
          <span class="text-[12px] opacity-80">{{ $rejected->method }} · {{ $rejected->transaction_id }} · {{ $rejected->reviewed_at?->diffForHumans() }}. Send the correct details below.</span>
        </div>
      @elseif ($pending)
        <div class="pay-alert is-pending">
          <strong>We already have your payment and are checking it.</strong>
          <span>{{ $pending->method }} · {{ $pending->transaction_id }} · sent {{ $pending->created_at->diffForHumans() }}.
            Send the form again only to correct the details — it replaces the one we have.</span>
        </div>
      @endif

      @if (trim($instructions) !== '')
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> 1 · How to pay</div>
          <div class="panel-body flex flex-col gap-3">
            <p class="whitespace-pre-line text-[15px] leading-relaxed text-neutral-800">{{ $instructions }}</p>
            <div class="flex items-center gap-3 border-t border-line pt-3 text-[14px]">
              <span class="text-neutral-600">Amount to send</span>
              <span class="price">{{ \App\Support\Lms::money($course->currentPrice()) }}</span>
            </div>
          </div>
        </section>
      @endif

      <form method="POST" action="{{ route('courses.pay', $course->slug) }}" enctype="multipart/form-data" class="panel">
        @csrf
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ trim($instructions) !== '' ? '2 · ' : '' }}Payment details</div>
        <div class="flex flex-col gap-5 p-5">

          <div class="field">
            <label>Paid with</label>
            @if (count($methods))
              <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($methods as $method)
                  <label class="option-card">
                    <input type="radio" name="method" value="{{ $method }}" required
                           @checked(old('method', $pending?->method ?? $rejected?->method ?? $methods[0]) === $method)>
                    <span class="option-card-label">{{ $method }}</span>
                  </label>
                @endforeach
              </div>
            @else
              <input name="method" class="input" required value="{{ old('method', $pending?->method) }}" placeholder="bKash, bank transfer…">
            @endif
            @error('method') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
              <label for="amount">Amount you sent ({{ \App\Support\Lms::setting('currency') }})</label>
              <input id="amount" type="number" name="amount" min="1" step="any" required class="input @error('amount') input-invalid @enderror"
                     value="{{ old('amount', $pending?->amount ? (float) $pending->amount : $course->currentPrice()) }}">
              @error('amount') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
              <label for="sender_number">Number / account you paid from</label>
              <input id="sender_number" name="sender_number" required maxlength="40" inputmode="tel" class="input @error('sender_number') input-invalid @enderror"
                     value="{{ old('sender_number', $pending?->sender_number ?? auth()->user()->phone) }}" placeholder="01XXXXXXXXX">
              @error('sender_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="field">
            <label for="transaction_id">Transaction ID</label>
            <input id="transaction_id" name="transaction_id" required maxlength="120" autocomplete="off"
                   class="input font-mono uppercase tracking-wider @error('transaction_id') input-invalid @enderror"
                   value="{{ old('transaction_id', $pending?->transaction_id) }}" placeholder="e.g. 8N7A6XK2QZ">
            <p class="mt-1.5 text-[11px] text-neutral-500">In the confirmation SMS or the app's history — usually 8 to 10 letters and numbers.</p>
            @error('transaction_id') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="field">
            <label>Screenshot of the payment <span class="font-normal text-neutral-500">(recommended — it gets you verified faster)</span></label>
            <label class="proof-drop" id="proof-drop">
              <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" id="proof-input">
              <span class="proof-drop-empty" id="proof-empty">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/></svg>
                <strong>Choose a screenshot</strong>
                <span>JPG, PNG or PDF · up to 5 MB</span>
              </span>
              <span class="proof-drop-chosen" id="proof-chosen" hidden>
                <img id="proof-preview" alt="">
                <span id="proof-name"></span>
                <span class="text-accent">Change</span>
              </span>
            </label>
            @error('proof') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="field">
            <label for="note">Anything we should know? <span class="font-normal text-neutral-500">(optional)</span></label>
            <textarea id="note" name="note" class="input !min-h-[64px] py-2" maxlength="500">{{ old('note', $pending?->note) }}</textarea>
          </div>

          <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button class="btn btn-primary btn-lg">{{ $pending ? 'Update payment details' : 'Submit for verification' }}</button>
            <a href="{{ route('courses.show', $course->slug) }}" class="btn btn-ghost">Cancel</a>
            <span class="ml-auto flex items-center gap-1.5 text-[12px] text-neutral-500">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              Only our team sees your screenshot
            </span>
          </div>
        </div>
      </form>
    </div>

    <aside class="flex flex-col gap-4">
      <div class="panel">
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Your order</div>
        @if ($course->thumbnail)
          <img src="{{ $course->thumbnail->url }}" alt="" class="aspect-video w-full border-b border-line object-cover">
        @endif
        <div class="flex flex-col gap-3 p-5">
          <div class="font-heading text-[16px] font-bold leading-snug">{{ $course->title }}</div>
          @if ($course->instructor)
            <div class="text-[13px] text-neutral-600">By {{ $course->instructor->name }}</div>
          @endif
          <div class="flex items-center justify-between border-t border-line pt-3">
            <span class="text-[13px] text-neutral-700">Total</span>
            <span>
              <span class="price">{{ \App\Support\Lms::money($course->currentPrice()) }}</span>
              @if ($course->onSale())
                <span class="price-was">{{ \App\Support\Lms::money($course->price) }}</span>
              @endif
            </span>
          </div>
        </div>
      </div>
      <div class="panel p-4 text-[13px] leading-relaxed text-neutral-700">
        <strong class="mb-1 block text-ink">What happens next?</strong>
        We match your transaction ID with our account — usually within a few hours. You get a notification and an email the moment it is verified, and the course opens in <a href="{{ route('student.dashboard') }}" class="text-accent">your dashboard</a>.
      </div>
    </aside>
  </div>

  <script>
    (() => {
      const input = document.getElementById('proof-input')
      input?.addEventListener('change', () => {
        const file = input.files?.[0]
        document.getElementById('proof-empty').hidden = !!file
        document.getElementById('proof-chosen').hidden = !file
        if (!file) return
        document.getElementById('proof-name').textContent = file.name
        const img = document.getElementById('proof-preview')
        img.hidden = !file.type.startsWith('image/')
        if (!img.hidden) img.src = URL.createObjectURL(file)
      })
    })()
  </script>
@endsection
