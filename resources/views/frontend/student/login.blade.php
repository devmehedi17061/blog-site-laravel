@extends('frontend.layout')

@section('title', $site->title('Sign in'))
@section('robots', 'noindex, follow')

@section('content')
  <div class="auth-card py-6">
    @include('frontend.partials.flash')

    <div class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Sign in</div>

      <form method="POST" action="{{ route('student.login.store') }}" class="flex flex-col gap-4 p-6">
        @csrf
        <div>
          <h1 class="!mb-1 text-[26px]">Welcome back</h1>
          <p class="text-[14px] text-neutral-600">Sign in to carry on with your courses.</p>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                 class="input @error('email') input-invalid @enderror">
          @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
          <label for="password" class="flex">
            Password
            <a href="{{ route('password.request') }}" class="ml-auto font-normal text-accent">Forgotten it?</a>
          </label>
          <input id="password" type="password" name="password" required autocomplete="current-password" class="input">
        </div>

        <label class="flex items-center gap-2 text-[13px] text-neutral-700">
          <input type="checkbox" name="remember" value="1" class="accent-[var(--color-accent)]"> Keep me signed in
        </label>

        <button class="btn btn-primary btn-lg w-full">Sign in</button>

        @if (\App\Support\Lms::setting('allow_registration'))
          <p class="border-t border-line pt-4 text-center text-[14px] text-neutral-700">
            New here? <a href="{{ route('student.register') }}" class="font-semibold text-accent">Create a free account</a>
          </p>
        @endif
      </form>
    </div>
  </div>
@endsection
