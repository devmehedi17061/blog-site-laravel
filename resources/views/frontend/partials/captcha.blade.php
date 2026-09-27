@php
  /**
   * The CAPTCHA from Settings → Spam protection, for one form. Renders
   * nothing when none is switched on.
   *
   *   Turnstile     — a small box Cloudflare fills in; usually no clicking.
   *   reCAPTCHA v3  — invisible: a token is fetched as the form is sent.
   *
   * @var \App\Support\SpamGuard $guard
   */
  $guard = app(\App\Support\SpamGuard::class);
  $provider = $guard->captchaProvider();
@endphp

@if ($provider === 'turnstile')
  <div class="cf-turnstile cms-captcha" data-sitekey="{{ $guard->siteKey() }}" data-action="{{ $action ?? 'form' }}"></div>
  @once
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  @endonce
  <noscript><p class="cms-help">This form needs JavaScript on, to check you are not a robot.</p></noscript>
@elseif ($provider === 'recaptcha')
  <input type="hidden" name="g-recaptcha-response" value="" data-recaptcha="{{ $guard->siteKey() }}">
  @once
    <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode($guard->siteKey()) }}" async defer></script>
    <script>
      // reCAPTCHA v3 has nothing to click: fetch a token as the form is
      // sent, then send it for real. One handler for every form on the page.
      document.addEventListener('submit', function (event) {
        var form = event.target
        var field = form.querySelector('input[data-recaptcha]')
        if (!field || form.dataset.recaptchaReady === '1') return

        event.preventDefault()
        if (!window.grecaptcha) { form.dataset.recaptchaReady = '1'; form.submit(); return }

        grecaptcha.ready(function () {
          grecaptcha.execute(field.dataset.recaptcha, { action: 'submit' }).then(function (token) {
            field.value = token
            form.dataset.recaptchaReady = '1'
            form.submit()
          })
        })
      }, true)
    </script>
  @endonce
  <p class="cms-help cms-captcha-note">
    Protected by reCAPTCHA — Google's
    <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> and
    <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a> apply.
  </p>
@endif
