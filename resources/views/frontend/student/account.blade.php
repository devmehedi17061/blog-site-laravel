@extends('frontend.student.shell')

@section('title', $site->title('Account'))
@section('panel_title', 'Account')

@section('panel')
  <form method="POST" action="{{ route('student.account.update') }}" class="grid max-w-[920px] gap-6 md:grid-cols-2">
    @csrf
    @method('PUT')

    <section class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Profile</div>
      <div class="flex flex-col gap-4 p-5">
        <div class="flex items-center gap-4 border-b border-line pb-4">
          <span class="sp-avatar !h-14 !w-14 !text-[18px]">{{ $user->initials }}</span>
          <div>
            <div class="font-heading text-[16px] font-bold">{{ $user->name }}</div>
            <div class="text-[12px] text-neutral-600">Member since {{ $site->date($user->created_at) }}</div>
          </div>
        </div>
        <div class="field">
          <label for="name">Full name</label>
          <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" class="input @error('name') input-invalid @enderror">
          <p class="mt-1.5 text-[11px] text-neutral-500">Printed on your certificates and receipts.</p>
          @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input @error('email') input-invalid @enderror">
          @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40" inputmode="tel" placeholder="01XXXXXXXXX" class="input @error('phone') input-invalid @enderror">
          <p class="mt-1.5 text-[11px] text-neutral-500">Filled in for you at the checkout.</p>
          @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Change password</div>
      <div class="flex flex-col gap-4 p-5">
        <div class="field">
          <label for="current_password">Current password</label>
          <input id="current_password" type="password" name="current_password" autocomplete="current-password" class="input @error('current_password') input-invalid @enderror">
          @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
          <label for="password">New password</label>
          <input id="password" type="password" name="password" autocomplete="new-password" class="input @error('password') input-invalid @enderror">
          @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="field">
          <label for="password_confirmation">New password again</label>
          <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="input">
        </div>
        <p class="text-[11px] text-neutral-500">Leave these blank to keep your password.</p>
      </div>
    </section>

    <div class="md:col-span-2">
      <button class="btn btn-primary btn-lg">Save changes</button>
    </div>
  </form>
@endsection
