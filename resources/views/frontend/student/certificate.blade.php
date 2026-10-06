@extends('frontend.layout')

@section('title', $site->title('Certificate — '.$enrollment->course->title))
@section('robots', 'noindex, nofollow')

@section('content')

  <div class="no-print mb-6 flex flex-wrap items-center gap-3">
    <a href="{{ route('student.dashboard') }}" class="btn btn-ghost btn-sm">← My courses</a>
    <span class="text-[13px] text-neutral-600">Use “Save as PDF” in the print window to keep a copy.</span>
    <button type="button" onclick="window.print()" class="btn btn-primary ml-auto">Print / save as PDF</button>
  </div>

  <div class="certificate">
    <div class="kicker text-[12px]">{{ $site->name() }}</div>
    <div class="font-heading text-[clamp(14px,1.8vw,18px)] font-bold tracking-[0.3em] text-neutral-700 uppercase">Certificate of completion</div>
    <p class="mt-4 text-[15px] text-neutral-600">This certifies that</p>
    <div class="certificate-name">{{ $enrollment->student->name }}</div>
    <p class="text-[15px] text-neutral-700">{{ $text }}</p>
    <div class="certificate-course">{{ $enrollment->course->title }}</div>

    <div class="certificate-foot">
      <div>
        <strong class="block text-ink">{{ $site->date($enrollment->completed_at) }}</strong>
        Date completed
      </div>
      <div class="text-center">
        <strong class="block text-ink">{{ $serial }}</strong>
        Certificate no.
      </div>
      <div class="text-right">
        <strong class="block text-ink">{{ $signer ?: $site->name() }}</strong>
        {{ $signer ? 'Instructor' : 'Issued by' }}
      </div>
    </div>
  </div>

@endsection
