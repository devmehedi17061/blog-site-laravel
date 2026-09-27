@extends('backend.layout')

@section('title', 'Two-factor check')

@section('body')
  <div class="flex min-h-screen items-center justify-center bg-bg p-4">
    <div class="w-full max-w-[400px] border border-line-heavy bg-surface shadow-lg"
         x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">

      <div class="flex items-center gap-3 bg-accent px-5 py-4 text-white">
        <span class="grid h-9 w-9 place-items-center bg-white/20" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
        </span>
        <div>
          <div class="font-heading text-[17px] font-extrabold tracking-tight">Two-factor check</div>
          <div class="text-[10px] tracking-[0.14em] uppercase opacity-85">Step 2 of 2</div>
        </div>
      </div>

      <form class="flex flex-col gap-4 p-6" method="POST" action="{{ route('admin.two-factor.store') }}" autocomplete="off">
        @csrf

        @if (session('status'))
          <div class="settings-callout !bg-[var(--color-success-100)] !border-[var(--color-success-800)] !text-[var(--color-success-800)]" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
          <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <template x-if="!recovery">
          <div class="flex flex-col gap-4">
            <p class="text-[13px] leading-relaxed text-neutral-700">
              @if ($method === 'email')
                We emailed a 6-digit code to <strong>{{ $email }}</strong>. It works for 10 minutes.
              @else
                Open your authenticator app and type the 6-digit code it shows for this site.
              @endif
            </p>

            <div class="field">
              <label for="code">Code</label>
              <input id="code" name="code" class="input !h-[50px] text-center font-mono !text-[24px] tracking-[0.4em]"
                     inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full">Verify and sign in</button>
          </div>
        </template>

        <template x-if="recovery">
          <div class="flex flex-col gap-4">
            <p class="text-[13px] leading-relaxed text-neutral-700">
              Lost your phone or cannot get the email? Enter one of the recovery codes you saved when you set up two-factor. Each works once.
            </p>
            <div class="field">
              <label for="recovery_code">Recovery code</label>
              <input id="recovery_code" name="recovery_code" class="input font-mono" placeholder="xxxx-xxxx" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full">Use recovery code</button>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line pt-4 text-[12px]">
          <button type="button" class="text-accent-700 underline" @click="recovery = !recovery"
                  x-text="recovery ? 'Use a code instead' : 'Use a recovery code'"></button>

          @if ($method === 'email')
            <button type="submit" form="resend-form" class="text-accent-700 underline" x-show="!recovery">Send a new code</button>
          @endif

          <a href="{{ route('admin.login') }}" class="ml-auto text-neutral-600 underline">Start over</a>
        </div>
      </form>

      @if ($method === 'email')
        <form id="resend-form" method="POST" action="{{ route('admin.two-factor.resend') }}" class="hidden">@csrf</form>
      @endif
    </div>
  </div>
@endsection
