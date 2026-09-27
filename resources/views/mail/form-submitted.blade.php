<x-mail::message>
# New {{ $form->name }} submission

@foreach ($answers as $label => $value)
**{{ $label }}**
{{ $value }}

@endforeach

<x-mail::button :url="url('/admin')">
Open the panel
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
