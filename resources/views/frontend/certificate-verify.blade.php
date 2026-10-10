@extends('frontend.layout')

@section('title', $site->title(__('Verify a certificate')))
@section('description', __('Check that a :site certificate of completion is genuine.', ['site' => $site->name()]))

@section('content')
  <div class="mx-auto max-w-[640px] py-4">
    <div class="kicker mb-1">{{ __('Certificates') }}</div>
    <h1 class="!mb-2">{{ __('Verify a certificate') }}</h1>
    <p class="mb-6 text-[15px] text-neutral-700">{{ __('Type the certificate number printed at the bottom of a :site certificate.', ['site' => $site->name()]) }}</p>

    <form method="GET" action="{{ route('certificates.verify') }}" class="mb-8 flex gap-2">
      <input name="serial" value="{{ $serial }}" required maxlength="40" placeholder="C-00012-261006-7KQ2PA" aria-label="{{ __('Certificate number') }}"
             class="input !h-[44px] font-mono uppercase tracking-wider">
      <button class="btn btn-primary btn-lg !h-[44px]">{{ __('Verify') }}</button>
    </form>

    @if ($serial !== '')
      @if ($enrollment)
        <div class="panel overflow-hidden">
          <div class="flex items-center gap-3 bg-success-800 px-5 py-3 text-white">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
            <strong class="font-heading text-[16px]">{{ __('Genuine certificate') }}</strong>
          </div>
          <dl class="grid gap-4 p-6 sm:grid-cols-2">
            <div><dt class="kicker mb-1">{{ __('Awarded to') }}</dt><dd class="font-heading text-[20px] font-extrabold">{{ $enrollment->student?->name }}</dd></div>
            <div><dt class="kicker mb-1">{{ __('Course') }}</dt><dd class="text-[16px] font-semibold">{{ $enrollment->course?->title }}</dd></div>
            <div><dt class="kicker mb-1">{{ __('Completed on') }}</dt><dd>{{ $site->date($enrollment->completed_at) }}</dd></div>
            <div><dt class="kicker mb-1">{{ __('Instructor') }}</dt><dd>{{ $enrollment->course?->instructor?->name ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="kicker mb-1">{{ __('Certificate number') }}</dt><dd class="font-mono">{{ $enrollment->certificateSerial() }}</dd></div>
          </dl>
        </div>
      @else
        <div class="pay-alert is-rejected">
          <strong>{{ __('No certificate with this number.') }}</strong>
          <span>{{ __('Check the number for typing mistakes. A certificate stops being valid if the course place was cancelled.') }}</span>
        </div>
      @endif
    @endif
  </div>
@endsection
