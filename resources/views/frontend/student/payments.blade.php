@extends('frontend.student.shell')

@section('title', $site->title(__('Payments')))
@section('panel_title', __('Payments'))

@section('panel')
  <div class="sp-tiles mb-6 !grid-cols-2 md:!grid-cols-4">
    <div class="sp-tile is-dark"><strong>{{ \App\Support\Lms::money($totalPaid) }}</strong><span>{{ __('Total paid') }}</span></div>
    <div class="sp-tile"><strong>{{ $payments->where('status', 'Verified')->count() }}</strong><span>{{ __('Verified') }}</span></div>
    <div class="sp-tile"><strong>{{ $payments->where('status', 'Pending')->count() }}</strong><span>{{ __('Being verified') }}</span></div>
    <div class="sp-tile"><strong>{{ $payments->where('status', 'Rejected')->count() }}</strong><span>{{ __('Not verified') }}</span></div>
  </div>

  @if ($payments->isEmpty())
    <div class="panel sp-empty">
      {{ __('No payments yet. Paid courses are bought from their course page.') }}
      <div class="mt-3"><a href="{{ route('courses.index') }}" class="btn btn-primary">{{ __('Browse courses') }}</a></div>
    </div>
  @else
    <div class="flex flex-col gap-4">
      @foreach ($payments as $payment)
        @php $state = strtolower($payment->status); @endphp
        <article class="panel">
          <div class="flex flex-wrap items-start gap-4 p-5">
            <div class="min-w-[220px] flex-1">
              <div class="mb-1 flex flex-wrap items-center gap-2">
                <span class="pay-status is-{{ $state }}">{{ __(['pending' => 'Being verified', 'verified' => 'Verified', 'rejected' => 'Not verified'][$state]) }}</span>
                <span class="text-[12px] text-neutral-500">{{ $payment->invoiceNumber() }}</span>
              </div>
              <a href="{{ $payment->course ? route('courses.show', $payment->course->slug) : '#' }}" class="font-heading text-[17px] font-bold text-ink no-underline hover:text-accent">{{ $payment->course?->title ?? __('Removed course') }}</a>
              <div class="mt-1 text-[13px] text-neutral-600">
                {{ __(':method from :number', ['method' => $payment->method, 'number' => $payment->sender_number ?: '—']) }} ·
                <span class="font-mono">{{ $payment->transaction_id }}</span> ·
                {{ $payment->created_at->translatedFormat('d M Y, h:i A') }}
              </div>
            </div>

            <div class="text-right">
              <div class="price">{{ \App\Support\Lms::money($payment->amount) }}</div>
              @if ($payment->isShort())
                <div class="text-[11px] font-semibold text-accent-700">{{ __('Course price') }} {{ \App\Support\Lms::money($payment->expected_amount) }}</div>
              @endif
            </div>
          </div>

          {{-- where it is: sent → checked → done --}}
          <div class="border-t border-line px-5 pt-1 pb-4">
            <ol class="pay-timeline">
              <li class="is-done">{{ __('Sent') }} · {{ $payment->created_at->translatedFormat('d M') }}</li>
              <li class="{{ $payment->isPending() ? 'is-wait' : 'is-done' }}">{{ $payment->isPending() ? __('Being checked…') : __('Checked').' · '.$payment->reviewed_at?->translatedFormat('d M') }}</li>
              <li class="{{ $payment->isVerified() ? 'is-done' : ($state === 'rejected' ? 'is-bad' : '') }}">{{ __($payment->isVerified() ? 'Course open' : ($state === 'rejected' ? 'Not verified' : 'Course opens')) }}</li>
            </ol>
          </div>

          @if ($state === 'rejected')
            <div class="pay-alert is-rejected mx-5 mb-5">
              <strong>{{ __('Why it was not verified') }}</strong>
              <span>{{ $payment->rejection_reason }}</span>
              @if (in_array($payment->id, $rejectedOpen, true) && $payment->course)
                <a href="{{ route('courses.checkout', $payment->course->slug) }}" class="btn btn-primary btn-sm mt-2 self-start">{{ __('Send the payment again') }}</a>
              @endif
            </div>
          @endif

          <div class="flex flex-wrap items-center gap-2 border-t border-line bg-muted px-5 py-3">
            @if ($payment->proof_path)
              <a href="{{ route('payments.proof', $payment) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">📎 {{ __('Your screenshot') }}</a>
            @endif
            @if ($payment->isPending() && $payment->course)
              <a href="{{ route('courses.checkout', $payment->course->slug) }}" class="btn btn-ghost btn-sm">{{ __('Correct the details') }}</a>
            @endif
            @if ($payment->isVerified())
              <a href="{{ route('student.receipt', $payment) }}" class="btn btn-secondary btn-sm ml-auto">{{ __('Receipt') }}</a>
              @if ($payment->course)
                <a href="{{ route('learn.show', $payment->course->slug) }}" class="btn btn-primary btn-sm">{{ __('Go to course') }}</a>
              @endif
            @endif
          </div>
        </article>
      @endforeach
    </div>
  @endif
@endsection
