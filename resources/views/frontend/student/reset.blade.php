@extends('frontend.layout')

@section('title', $site->title('Choose a new password'))
@section('robots', 'noindex, nofollow')

@section('content')
  <div class="auth-card py-6">
    <div class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> New password</div>
      <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4 p-6">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"
                 class="input @error('email') input-invalid @enderror">
          @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="password">New password</label>
          <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                 class="input @error('password') input-invalid @enderror">
          <p class="mt-1.5 text-[11px] text-neutral-500">At least 8 characters, with a letter and a number.</p>
          @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="password_confirmation">New password again</label>
          <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>

        <button class="btn btn-primary btn-lg w-full">Save the new password</button>
      </form>
    </div>
  </div>
@endsection
