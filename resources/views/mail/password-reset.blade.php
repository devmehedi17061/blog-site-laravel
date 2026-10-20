<x-mail::message>
# Reset your password

Hi {{ $user->name }}, someone asked to reset the password of your **{{ config('app.name') }}** admin account.

<x-mail::button :url="$url">
Choose a new password
</x-mail::button>

The link works once, for the next {{ $minutes }} minutes.@if ($ip) It was asked for from {{ $ip }}.@endif

If this was not you, ignore this email — your password stays as it is.
</x-mail::message>
