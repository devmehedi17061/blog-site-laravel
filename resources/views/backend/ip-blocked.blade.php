<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Access restricted</title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; box-sizing: border-box;
      background: #f2f1f1; color: #1c1a19; font: 15px/1.6 system-ui, sans-serif; }
    main { max-width: 460px; padding: 28px; background: #fff; border: 1px solid #201e1d; }
    h1 { margin: 0 0 10px; font-size: 22px; }
    p { margin: 0 0 10px; color: #605d5d; }
    code { padding: 2px 6px; background: #f7f6f6; border: 1px solid #e5e2e2; }
  </style>
</head>
<body>
  <main>
    <h1>Access restricted</h1>
    <p>The admin panel only accepts connections from approved addresses, and yours — <code>{{ $ip }}</code> — is not one of them.</p>
    <p>If you should have access, ask a site administrator to add your address under Security → IP rules.</p>
  </main>
</body>
</html>
