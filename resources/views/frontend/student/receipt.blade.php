@extends('frontend.student.shell')

@section('title', $site->title('Receipt '.$payment->invoiceNumber()))
@section('panel_kicker', 'Payments')
@section('panel_title', 'Receipt')
@section('panel_actions')
  <a href="{{ route('student.payments') }}" class="btn btn-ghost no-print">← Payments</a>
  <button type="button" onclick="window.print()" class="btn btn-primary no-print">Print / save as PDF</button>
@endsection

@push('head')
  <style>@media print { @page { size: A4 portrait; margin: 14mm; } .sp-head { display: none; } }</style>
@endpush

@section('panel')
  <div class="receipt">
    <div class="receipt-head">
      <div>
        <div class="font-heading text-[22px] font-extrabold">{{ $site->name() }}</div>
        @if ($site->get('contact_email')) <div class="text-[13px] text-neutral-300">{{ $site->get('contact_email') }}</div> @endif
        @if ($site->get('phone')) <div class="text-[13px] text-neutral-300">{{ $site->get('phone') }}</div> @endif
      </div>
      <div class="text-right">
        <div class="kicker !text-accent-300">Payment receipt</div>
        <div class="font-heading text-[20px] font-extrabold">{{ $payment->invoiceNumber() }}</div>
        <div class="text-[13px] text-neutral-300">{{ $payment->reviewed_at?->format('d M Y') }}</div>
      </div>
    </div>

    <div class="receipt-body">
      <div class="mb-6 flex flex-wrap justify-between gap-6">
        <div>
          <div class="kicker mb-1">Billed to</div>
          <div class="font-semibold">{{ $payment->student?->name }}</div>
          <div class="text-[13px] text-neutral-600">{{ $payment->student?->email }}</div>
          @if ($payment->student?->phone) <div class="text-[13px] text-neutral-600">{{ $payment->student->phone }}</div> @endif
        </div>
        <span class="receipt-stamp self-center">Paid · Verified</span>
      </div>

      <table class="receipt-table">
        <thead><tr><th>Course</th><th class="!text-right">Amount</th></tr></thead>
        <tbody>
          <tr>
            <td>{{ $payment->course?->title }}<div class="text-[12px] text-neutral-500">Lifetime access</div></td>
            <td class="text-right font-semibold">{{ \App\Support\Lms::money($payment->amount) }}</td>
          </tr>
          <tr>
            <td class="!border-0 text-right font-heading font-bold">Total paid</td>
            <td class="!border-0 text-right font-heading text-[18px] font-extrabold">{{ \App\Support\Lms::money($payment->amount) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-6 grid gap-4 border-t border-line pt-5 text-[13px] sm:grid-cols-3">
        <div><div class="kicker mb-1">Method</div>{{ $payment->method }}</div>
        <div><div class="kicker mb-1">Paid from</div>{{ $payment->sender_number ?: '—' }}</div>
        <div><div class="kicker mb-1">Transaction ID</div><span class="font-mono">{{ $payment->transaction_id }}</span></div>
        <div><div class="kicker mb-1">Paid on</div>{{ $payment->created_at->format('d M Y, h:i A') }}</div>
        <div><div class="kicker mb-1">Verified on</div>{{ $payment->reviewed_at?->format('d M Y, h:i A') }}</div>
      </div>

      <p class="mt-8 text-center text-[12px] text-neutral-500">Thank you for learning with {{ $site->name() }}. This receipt was issued electronically and is valid without a signature.</p>
    </div>
  </div>
@endsection
