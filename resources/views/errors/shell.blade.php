{{--
  The frame for error pages that must work when nothing else does: no
  database, no menu, no build step. A server error (500) may be the
  database being down, so nothing here may depend on it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>@yield('title') · {{ config('app.name') }}</title>
  <style>
    html, body { height: 100%; margin: 0; }
    body { display: grid; place-items: center; padding: 24px; box-sizing: border-box;
      background: #f2f1f1; color: #1c1a19; font: 16px/1.6 Archivo, system-ui, sans-serif; }
    main { width: 100%; max-width: 520px; padding: 32px; background: #fff; border: 1px solid #201e1d;
      box-shadow: 0 8px 24px rgba(28, 26, 25, .08); }
    .code { display: inline-block; margin-bottom: 14px; padding: 2px 10px; background: #ec3013; color: #fff;
      font-weight: 800; font-size: 13px; letter-spacing: .08em; }
    h1 { margin: 0 0 10px; font-size: 26px; line-height: 1.2; }
    p { margin: 0 0 12px; color: #605d5d; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
    .actions a, .actions button { padding: 10px 18px; border: 1px solid #1c1a19; background: #fff; color: #1c1a19;
      font: 700 14px/1 Archivo, system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .actions .primary { background: #ec3013; border-color: #ec3013; color: #fff; }
  </style>
</head>
<body>
  <main>
    <span class="code">@yield('code')</span>
    <h1>@yield('heading')</h1>
    @yield('body')
    <div class="actions">
      @yield('actions')
    </div>
  </main>
</body>
</html>
