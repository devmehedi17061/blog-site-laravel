<x-mail::message>
# {{ $purpose === 'setup' ? 'Confirm two-factor sign-in' : 'Your sign-in code' }}

Hi {{ $user->name }}, enter this code to {{ $purpose === 'setup' ? 'switch on emailed sign-in codes' : 'finish signing in' }} to **{{ config('app.name') }}**:

<x-mail::panel>
<span style="font-size:28px;font-weight:700;letter-spacing:6px">{{ $code }}</span>
</x-mail::panel>

It works once, for the next {{ $minutes }} minutes.

If you did not just try to sign in, someone knows your password — change it straight away.
</x-mail::message>
