@extends('frontend.layout')

@section('title', $site->title(__('Checkout').' — '.$course->title))
@section('robots', 'noindex, nofollow')

@section('content')

  <nav class="mb-6 text-[13px] text-neutral-600" aria-label="{{ __('Breadcrumb') }}">
    <a href="{{ route('courses.index') }}" class="text-accent no-underline">{{ __('Courses') }}</a>
    <span class="mx-2">/</span>
    <a href="{{ route('courses.show', $course->slug) }}" class="text-accent no-underline">{{ $course->title }}</a>
    <span class="mx-2">/</span>
    <span>{{ __('Checkout') }}</span>
  </nav>

  {{-- the three steps, so the student knows what happens after the form --}}
  <ol class="checkout-steps mb-8">
    <li class="is-on"><span>1</span> {{ __('Send the fee') }}</li>
    <li class="is-on"><span>2</span> {{ __('Tell us the transaction') }}</li>
    <li><span>3</span> {{ __('We verify — the course opens') }}</li>
  </ol>

  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">

    <div class="flex flex-col gap-6">
      @if ($rejected)
        <div class="pay-alert is-rejected">
          <strong>{{ __('Your last payment could not be verified.') }}</strong>
          <span>{{ __('Reason: :reason', ['reason' => $rejected->rejection_reason]) }}</span>
          <span class="text-[12px] opacity-80">{{ $rejected->method }} · {{ $rejected->transaction_id }} · {{ $rejected->reviewed_at?->diffForHumans() }}. {{ __('Send the correct details below.') }}</span>
        </div>
      @elseif ($pending)
        <div class="pay-alert is-pending">
          <strong>{{ __('We already have your payment and are checking it.') }}</strong>
          <span>{{ $pending->method }} · {{ $pending->transaction_id }} · {{ __('sent :when', ['when' => $pending->created_at->diffForHumans()]) }}.
            {{ __('Send the form again only to correct the details — it replaces the one we have.') }}</span>
        </div>
      @endif

      @include('frontend.partials.flash')

      @if ($price['total'] <= 0)
        {{-- the coupon covers the whole price: nothing to pay or check --}}
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('Nothing to pay') }}</div>
          <div class="panel-body flex flex-col items-start gap-4">
            <p class="text-[15px] leading-relaxed text-neutral-800">
              {!! __('Coupon :code covers the whole price.', ['code' => '<strong class="font-mono">'.e($coupon->code).'</strong>']) !!}
              {{ __('The course opens as soon as you confirm.') }}
            </p>
            <form method="POST" action="{{ route('courses.pay', $course->slug) }}">
              @csrf
              <button class="btn btn-primary btn-lg">{{ __('Get the course for free') }} →</button>
            </form>
          </div>
        </section>
      @else
      @if (trim($instructions) !== '')
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> 1 · {{ __('How to pay') }}</div>
          <div class="panel-body flex flex-col gap-3">
            <p class="whitespace-pre-line text-[15px] leading-relaxed text-neutral-800">{{ $instructions }}</p>
            <div class="flex items-center gap-3 border-t border-line pt-3 text-[14px]">
              <span class="text-neutral-600">{{ __('Amount to send') }}</span>
              <span class="price">{{ \App\Support\Lms::money($price['total']) }}</span>
              @if ($price['discount'] > 0)
                <span class="tag tag-success">{{ __('Coupon :code', ['code' => $coupon->code]) }}</span>
              @endif
            </div>
          </div>
        </section>
      @endif

      <form method="POST" action="{{ route('courses.pay', $course->slug) }}" enctype="multipart/form-data" class="panel">
        @csrf
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ trim($instructions) !== '' ? '2 · ' : '' }}{{ __('Payment details') }}</div>
        <div class="flex flex-col gap-5 p-5">

          <div class="field">
            <label>{{ __('Paid with') }}</label>
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
              <input name="method" class="input" required value="{{ old('method', $pending?->method) }}" placeholder="{{ __('bKash, bank transfer…') }}">
            @endif
            @error('method') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="field">
              <label for="amount">{{ __('Amount you sent (:currency)', ['currency' => \App\Support\Lms::setting('currency')]) }}</label>
              <input id="amount" type="number" name="amount" min="1" step="any" required class="input @error('amount') input-invalid @enderror"
                     value="{{ old('amount', $pending?->amount ? (float) $pending->amount : $price['total']) }}">
              @error('amount') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="field">
              <label for="sender_number">{{ __('Number / account you paid from') }}</label>
              <input id="sender_number" name="sender_number" required maxlength="40" inputmode="tel" class="input @error('sender_number') input-invalid @enderror"
                     value="{{ old('sender_number', $pending?->sender_number ?? auth()->user()->phone) }}" placeholder="01XXXXXXXXX">
              @error('sender_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>
          </div>

          <div class="field">
            <label for="transaction_id">{{ __('Transaction ID') }}</label>
            <input id="transaction_id" name="transaction_id" required maxlength="120" autocomplete="off"
                   class="input font-mono uppercase tracking-wider @error('transaction_id') input-invalid @enderror"
                   value="{{ old('transaction_id', $pending?->transaction_id) }}" placeholder="{{ __('e.g. 8N7A6XK2QZ') }}">
            <p class="mt-1.5 text-[11px] text-neutral-500">{{ __('In the confirmation SMS or the app\'s history — usually 8 to 10 letters and numbers.') }}</p>
            @error('transaction_id') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="field">
            <label>{{ __('Screenshot of the payment') }} <span class="font-normal text-neutral-500">{{ __('(recommended — it gets you verified faster)') }}</span></label>
            <label class="proof-drop" id="proof-drop">
              <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" id="proof-input">
              <span class="proof-drop-empty" id="proof-empty">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/></svg>
                <strong>{{ __('Choose a screenshot') }}</strong>
                <span>{{ __('JPG, PNG or PDF · up to 5 MB') }}</span>
              </span>
              <span class="proof-drop-chosen" id="proof-chosen" hidden>
                <img id="proof-preview" alt="">
                <span id="proof-name"></span>
                <span class="text-accent">{{ __('Change') }}</span>
              </span>
            </label>
            @error('proof') <p class="field-error">{{ $message }}</p> @enderror
          </div>

          <div class="field">
            <label for="note">{{ __('Anything we should know?') }} <span class="font-normal text-neutral-500">{{ __('(optional)') }}</span></label>
            <textarea id="note" name="note" class="input !min-h-[64px] py-2" maxlength="500">{{ old('note', $pending?->note) }}</textarea>
          </div>

          <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button class="btn btn-primary btn-lg">{{ $pending ? __('Update payment details') : __('Submit for verification') }}</button>
            <a href="{{ route('courses.show', $course->slug) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
            <span class="ml-auto flex items-center gap-1.5 text-[12px] text-neutral-500">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
              {{ __('Only our team sees your screenshot') }}
            </span>
          </div>
        </div>
      </form>
      @endif
    </div>

    <aside class="flex flex-col gap-4">
      <div class="panel">
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('Your order') }}</div>
        @if ($course->thumbnail)
          <img src="{{ $course->thumbnail->url }}" alt="" class="aspect-video w-full border-b border-line object-cover">
        @endif
        <div class="flex flex-col gap-3 p-5">
          <div class="font-heading text-[16px] font-bold leading-snug">{{ $course->title }}</div>
          @if ($course->instructor)
            <div class="text-[13px] text-neutral-600">{{ __('By :name', ['name' => $course->instructor->name]) }}</div>
          @endif
          {{-- the bill --}}
          <dl class="flex flex-col gap-1.5 border-t border-line pt-3 text-[13px]">
            <div class="flex justify-between">
              <dt class="text-neutral-700">{{ __('Course price') }}</dt>
              <dd>
                @if ($course->onSale()) <span class="price-was !ml-0 mr-1">{{ \App\Support\Lms::money($course->price) }}</span> @endif
                {{ \App\Support\Lms::money($price['price']) }}
              </dd>
            </div>
            @if ($coupon)
              <div class="flex justify-between text-success-800">
                <dt>{{ __('Coupon') }} <span class="font-mono font-bold">{{ $coupon->code }}</span> ({{ $coupon->label() }})</dt>
                <dd class="font-semibold">−{{ \App\Support\Lms::money($price['discount']) }}</dd>
              </div>
            @endif
            <div class="mt-1 flex items-center justify-between border-t border-line pt-2">
              <dt class="font-semibold text-ink">{{ __('Total') }}</dt>
              <dd class="price {{ $price['total'] <= 0 ? 'price-free' : '' }}">{{ $price['total'] <= 0 ? __('Free') : \App\Support\Lms::money($price['total']) }}</dd>
            </div>
          </dl>
        </div>

        {{-- the coupon box --}}
        <div class="border-t border-line bg-muted p-4">
          @if ($coupon)
            <div class="flex items-center gap-2">
              <span class="coupon-chip">🏷 {{ $coupon->code }}</span>
              <span class="text-[12px] text-success-800">{{ __(':discount applied', ['discount' => $coupon->label()]) }}</span>
              <form method="POST" action="{{ route('courses.coupon.remove', $course->slug) }}" class="ml-auto">
                @csrf @method('DELETE')
                <button class="text-[12px] font-semibold text-accent-700 underline">{{ __('Remove') }}</button>
              </form>
            </div>
          @else
            <form method="POST" action="{{ route('courses.coupon.apply', $course->slug) }}" class="flex flex-col gap-1.5">
              @csrf
              <label for="coupon-code" class="text-[12px] font-semibold text-neutral-700">{{ __('Have a coupon?') }}</label>
              <div class="flex gap-2">
                <input id="coupon-code" name="code" value="{{ old('code') }}" maxlength="40" autocomplete="off" placeholder="{{ __('CODE') }}"
                       class="input !h-[34px] font-mono uppercase tracking-wider @error('code') input-invalid @enderror">
                <button class="btn btn-secondary btn-sm !h-[34px]">{{ __('Apply') }}</button>
              </div>
              @error('code') <p class="field-error">{{ $message }}</p> @enderror
              @if (session('coupon_error')) <p class="field-error">{{ session('coupon_error') }}</p> @endif
            </form>
          @endif
        </div>
      </div>
      <div class="panel p-4 text-[13px] leading-relaxed text-neutral-700">
        <strong class="mb-1 block text-ink">{{ __('What happens next?') }}</strong>
        {!! __('We match your transaction ID with our account — usually within a few hours. You get a notification and an email the moment it is verified, and the course opens in :link.', ['link' => '<a href="'.e(route('student.dashboard')).'" class="text-accent">'.e(__('your dashboard')).'</a>']) !!}
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
