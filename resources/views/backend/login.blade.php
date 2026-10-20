@extends('backend.layout')

@section('title', 'Sign in')

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

      <form class="flex flex-col gap-4 p-6" method="POST" action="{{ route('admin.login.store') }}">
        @csrf

        <div>
          <div class="kicker mb-1.5">Sign in</div>
          <h4 class="!mb-1">Welcome back</h4>
          <p class="text-[13px] text-neutral-600">Use your admin account to continue.</p>
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
                 autocomplete="username" required autofocus
                 value="{{ old('email') }}" placeholder="you@example.com">
        </div>

        {{-- The eye shows what was typed, to catch a typo before a wrong
             password counts towards the sign-in lockout. --}}
        <div class="field" x-data="{ show: false }">
          <label for="password">Password</label>
          <div class="password-field">
            <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="input"
                   autocomplete="current-password" required placeholder="••••••••">
            <button type="button" class="password-eye" @click="show = !show; $nextTick(() => document.getElementById('password').focus())"
                    :aria-pressed="show" :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
              <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>
              </svg>
              <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9.9 4.2A10 10 0 0 1 12 4c6.4 0 10 8 10 8a17 17 0 0 1-2.2 3.2"></path>
                <path d="M6.6 6.6C3.6 8.5 2 12 2 12s3.6 8 10 8a9.7 9.7 0 0 0 5.4-1.6"></path>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path><path d="m2 2 20 20"></path>
              </svg>
            </button>
          </div>
        </div>

        <label class="flex items-center gap-2 text-[13px] text-neutral-700">
          <input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-[var(--color-accent)]">
          Keep me signed in
        </label>

        {{-- Settings → Spam protection can put the CAPTCHA here too. --}}
        @if (app(\App\Support\SpamGuard::class)->protectsLogin())
          @include('frontend.partials.captcha', ['action' => 'login'])
        @endif

        <button type="submit" class="btn btn-primary btn-lg w-full">Sign in</button>

        <a href="{{ route('admin.password.request') }}" class="text-center text-[13px] text-neutral-700 underline underline-offset-2 hover:text-accent">Forgot your password?</a>


      </form>
    </div>
  </div>
@endsection
