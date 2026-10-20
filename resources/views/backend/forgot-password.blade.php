@extends('backend.layout')

@section('title', 'Forgot your password?')

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

      <form class="flex flex-col gap-4 p-6" method="POST" action="{{ route('admin.password.email') }}">
        @csrf

        <div>
          <div class="kicker mb-1.5">Password</div>
          <h4 class="!mb-1">Forgot your password?</h4>
          <p class="text-[13px] text-neutral-600">Enter your account's email address and we will send a link to choose a new one.</p>
        </div>

        @if (session('status'))
          <div class="alert" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
          <div class="alert">{{ $errors->first() }}</div>
        @endif

        <div class="field">
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" class="input @error('email') input-invalid @enderror"
                 autocomplete="username" required autofocus value="{{ old('email') }}" placeholder="you@example.com">
        </div>

        @if (app(\App\Support\SpamGuard::class)->protectsLogin())
          @include('frontend.partials.captcha', ['action' => 'login'])
        @endif

        <button type="submit" class="btn btn-primary btn-lg w-full">Email me a link</button>
        <a href="{{ route('admin.login') }}" class="text-center text-[13px] text-neutral-700 underline underline-offset-2 hover:text-accent">← Back to sign in</a>
      </form>
    </div>
  </div>
@endsection
