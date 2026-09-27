<x-mail::message>
# It works

This is a test email from **{{ $fromName }}**, sent through **{{ $via }}**.

If you are reading it, form notifications and every other email the site sends will arrive the same way.

Sent by {{ $sentBy }} from Settings → Email, {{ now()->format('j M Y, H:i') }}.
</x-mail::message>
