<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>{{ $site->name() }} — {{ __('back soon') }}</title>
  @if ($site->get('favicon'))
    <link rel="icon" href="{{ $site->get('favicon') }}">
  @endif
  {{-- Self-contained: nothing here should depend on the build or the menu. --}}
  <style>
    html, body { height: 100%; margin: 0; }
    body { display: grid; place-items: center; padding: 24px; box-sizing: border-box;
      background: #f2f1f1; color: #1c1a19; font: 16px/1.6 Archivo, system-ui, sans-serif; }
    main { max-width: 520px; text-align: center; }
    .mark { display: inline-grid; place-items: center; width: 56px; height: 56px; margin-bottom: 22px;
      background: #ec3013; color: #fff; font-weight: 800; font-size: 15px; }
    img { max-height: 60px; max-width: 240px; margin-bottom: 22px; }
    h1 { margin: 0 0 12px; font-size: 30px; line-height: 1.2; }
    p { margin: 0; color: #605d5d; white-space: pre-line; }
    a { color: #ae1800; }
  </style>
</head>
<body>
  <main>
    @if ($site->get('logo'))
      <img src="{{ $site->get('logo') }}" alt="{{ $site->name() }}">
    @else
      <div class="mark" aria-hidden="true">{{ $site->initials() }}</div>
    @endif
    <h1>{{ __('We\'ll be back soon') }}</h1>
    <p>{{ $site->get('maintenance_message') }}</p>
    @if ($site->get('contact_email'))
      <p style="margin-top:18px">{{ __('Need us now?') }} <a href="mailto:{{ $site->get('contact_email') }}">{{ $site->get('contact_email') }}</a></p>
    @endif
  </main>
</body>
</html>
