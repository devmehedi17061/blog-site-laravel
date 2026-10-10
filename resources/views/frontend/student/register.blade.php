@extends('frontend.layout')

@section('title', $site->title(__('Create an account')))

@section('content')
  <div class="auth-card py-6">
    <div class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> {{ __('Create an account') }}</div>

      <form method="POST" action="{{ route('student.register.store') }}" class="flex flex-col gap-4 p-6">
        @csrf
        <div>
          <h1 class="!mb-1 text-[26px]">{{ __('Start learning') }}</h1>
          <p class="text-[14px] text-neutral-600">{{ __('One free account for every course on :site.', ['site' => $site->name()]) }}</p>
        </div>

        {{-- Hidden from people; a bot that fills it is turned away. --}}
        <div aria-hidden="true" style="position:absolute;left:-9999px">
          <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="field">
          <label for="name">{{ __('Full name') }}</label>
          <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="120"
                 class="input @error('name') input-invalid @enderror">
          @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="email">{{ __('Email') }}</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                 class="input @error('email') input-invalid @enderror">
          @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="password">{{ __('Password') }}</label>
          <input id="password" type="password" name="password" required autocomplete="new-password"
                 class="input @error('password') input-invalid @enderror">
          <p class="mt-1.5 text-[11px] text-neutral-500">{{ __('At least 8 characters, with a letter and a number.') }}</p>
          @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="password_confirmation">{{ __('Password again') }}</label>
          <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>

        <button class="btn btn-primary btn-lg w-full">{{ __('Create account') }}</button>

        <p class="border-t border-line pt-4 text-center text-[14px] text-neutral-700">
          {{ __('Already have one?') }} <a href="{{ route('student.login') }}" class="font-semibold text-accent">{{ __('Sign in') }}</a>
        </p>
      </form>
    </div>
  </div>
@endsection
