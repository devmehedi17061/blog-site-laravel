@extends('backend.layout')

@section('title', 'Choose a new password')

@section('body')
  <div class="flex min-h-screen items-center justify-center bg-bg p-4">
    <div class="w-full max-w-[400px] border border-line-heavy bg-surface shadow-lg">

      <div class="flex items-center gap-3 bg-accent px-5 py-4 text-white">
        <span class="grid h-9 w-9 place-items-center bg-white/20 font-heading text-[13px] font-bold">CMS</span>
        <div>
          <div class="font-heading text-[17px] font-extrabold tracking-tight">CMS Admin</div>
          <div class="text-[10px] tracking-[0.14em] uppercase opacity-85">Control panel</div>
        </div>
      </div>

      <form class="flex flex-col gap-4 p-6" method="POST" action="{{ route('admin.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
          <div class="kicker mb-1.5">Password</div>
          <h4 class="!mb-1">Choose a new password</h4>
          <p class="text-[13px] text-neutral-600">{{ app(\App\Support\Security::class)->passwordHint() }}</p>
        </div>

        @if ($errors->any())
          <div class="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" class="input" autocomplete="username" required value="{{ old('email', $email) }}">
        </div>

        <div class="field">
          <label for="password">New password</label>
          <input id="password" name="password" type="password" class="input @error('password') input-invalid @enderror" autocomplete="new-password" required autofocus>
        </div>

        <div class="field">
          <label for="password_confirmation">New password, again</label>
          <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-full">Save the new password</button>
      </form>
    </div>
  </div>
@endsection
