@extends('frontend.layout')

@section('title', $site->title(__('Reset your password')))
@section('robots', 'noindex, follow')

@section('content')
  <div class="auth-card py-6">
    @include('frontend.partials.flash')

    <div class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('Forgotten password') }}</div>
      <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4 p-6">
        @csrf
        <p class="text-[14px] leading-relaxed text-neutral-700">
          {{ __('Type the email you signed up with and we will send you a link to choose a new password.') }}
        </p>
        <div class="field">
          <label for="email">{{ __('Email') }}</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                 class="input @error('email') input-invalid @enderror">
          @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button class="btn btn-primary btn-lg w-full">{{ __('Send the link') }}</button>
        <p class="text-center text-[14px]"><a href="{{ route('student.login') }}" class="text-accent">← {{ __('Back to sign in') }}</a></p>
      </form>
    </div>
  </div>
@endsection
