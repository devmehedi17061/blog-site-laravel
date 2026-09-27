{{-- Settings → Spam protection. One root element: it sits inside an x-if. --}}
<form class="settings-stack" @submit.prevent="saveGroup('spam')" autocomplete="off">

  {{-- what it has done --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Blocked this month</h3>
      <p>Spam stopped since the start of <span x-text="spamStats.month"></span>. Bots are told "thanks" like everyone else, so they never learn they were caught.</p>
    </div>
    <div class="spam-stats p-5">
      <div class="spam-total">
        <span class="spam-total-number" x-text="spamStats.total"></span>
        <span>blocked</span>
      </div>
      <template x-for="reason in spamStats.reasons" :key="reason.key">
        <div class="spam-reason" :class="reason.count === 0 && 'is-zero'" :data-reason="reason.key">
          <span class="spam-reason-count" x-text="reason.count"></span>
          <span x-text="reason.label"></span>
        </div>
      </template>
    </div>
  </section>

  {{-- CAPTCHA --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>CAPTCHA</h3>
      <p>A check that the visitor is a person, on every form. Needs free keys from the service you pick.</p>
    </div>
    <div class="p-5">
      <div class="provider-grid" role="radiogroup" aria-label="CAPTCHA">
        <template x-for="c in captchas" :key="c.value">
          <button type="button" role="radio" class="provider-card" :aria-checked="spam.captcha === c.value"
                  :class="spam.captcha === c.value && 'is-active'" :disabled="!can.update"
                  :data-captcha="c.value" @click="chooseCaptcha(c.value)">
            <span class="provider-mono" x-text="c.mono"></span>
            <span class="provider-label" x-text="c.label"></span>
            <span class="provider-summary" x-text="c.summary"></span>
          </button>
        </template>
      </div>

      <template x-if="spam.captcha === 'turnstile'">
        <div class="settings-grid mt-5">
          <div class="settings-grid-full settings-steps">
            <strong>Getting the keys:</strong>
            <ol>
              <li>Sign in at <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com → Turnstile ↗</a> (a free account; the site does not need to be on Cloudflare).</li>
              <li>Add a widget for <strong x-text="location.hostname"></strong>, mode "Managed".</li>
              <li>Copy the <strong>Site Key</strong> and <strong>Secret Key</strong> here.</li>
            </ol>
          </div>
          <div class="field">
            <label for="spam-turnstile-site">Site key</label>
            <input id="spam-turnstile-site" class="input font-mono" :class="fieldError('turnstile_site_key') && 'input-invalid'"
                   x-model.trim="spam.turnstile_site_key" placeholder="0x4AAAAAAA…" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('turnstile_site_key')" x-text="fieldError('turnstile_site_key')"></p>
          </div>
          <div class="field">
            <label for="spam-turnstile-secret">Secret key</label>
            <input id="spam-turnstile-secret" type="password" class="input font-mono" :class="fieldError('turnstile_secret') && 'input-invalid'"
                   x-model.trim="spam.turnstile_secret" autocomplete="new-password" :disabled="!can.update"
                   :placeholder="spam.turnstile_secret_set ? '•••••••• saved — leave blank to keep it' : '0x4AAAAAAA…'">
            <p class="field-error" x-show="fieldError('turnstile_secret')" x-text="fieldError('turnstile_secret')"></p>
          </div>
        </div>
      </template>

      <template x-if="spam.captcha === 'recaptcha'">
        <div class="settings-grid mt-5">
          <div class="settings-grid-full settings-steps">
            <strong>Getting the keys:</strong>
            <ol>
              <li>Open <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin ↗</a> and register a site.</li>
              <li>Choose <strong>reCAPTCHA v3</strong> (score based) and add <strong x-text="location.hostname"></strong> as a domain.</li>
              <li>Copy the <strong>Site key</strong> and <strong>Secret key</strong> here.</li>
            </ol>
          </div>
          <div class="field">
            <label for="spam-recaptcha-site">Site key</label>
            <input id="spam-recaptcha-site" class="input font-mono" :class="fieldError('recaptcha_site_key') && 'input-invalid'"
                   x-model.trim="spam.recaptcha_site_key" placeholder="6Lc…" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('recaptcha_site_key')" x-text="fieldError('recaptcha_site_key')"></p>
          </div>
          <div class="field">
            <label for="spam-recaptcha-secret">Secret key</label>
            <input id="spam-recaptcha-secret" type="password" class="input font-mono" :class="fieldError('recaptcha_secret') && 'input-invalid'"
                   x-model.trim="spam.recaptcha_secret" autocomplete="new-password" :disabled="!can.update"
                   :placeholder="spam.recaptcha_secret_set ? '•••••••• saved — leave blank to keep it' : '6Lc…'">
            <p class="field-error" x-show="fieldError('recaptcha_secret')" x-text="fieldError('recaptcha_secret')"></p>
          </div>
          <div class="field">
            <label for="spam-recaptcha-score">
              Minimum score: <strong x-text="(spam.recaptcha_min_score / 100).toFixed(1)"></strong>
            </label>
            <input id="spam-recaptcha-score" type="range" min="10" max="90" step="10" class="w-full accent-[var(--color-accent)]"
                   x-model.number="spam.recaptcha_min_score" :disabled="!can.update">
            <p class="mt-1 text-[11px] text-neutral-500">Google's default is 0.5. Higher blocks more bots — and a few more real people.</p>
          </div>
        </div>
      </template>

      <template x-if="spam.captcha !== 'none'">
        <div class="mt-4 flex flex-col gap-3">
          <div class="flex flex-wrap items-center gap-3" x-show="can.update">
            <button type="button" class="btn btn-secondary btn-sm" @click="checkKey()" :disabled="keyChecking"
                    x-text="keyChecking ? 'Checking…' : 'Check secret key'"></button>
            <template x-if="keyCheck">
              <span class="test-result !flex-none !py-1.5" :class="keyCheck.ok === true ? 'is-ok' : keyCheck.ok === null ? 'is-neutral' : 'is-bad'" x-text="keyCheck.message"></span>
            </template>
          </div>

          <label class="switch-row">
            <input type="checkbox" class="switch" x-model="spam.protect_login" :disabled="!can.update">
            <span>
              <strong>Also protect the admin sign-in page</strong>
              <span>Stops bots guessing passwords. If the keys stop working you can still sign in: the check only runs while both keys are set.</span>
            </span>
          </label>
        </div>
      </template>
    </div>
  </section>

  {{-- built-in --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Built-in protection</h3>
      <p>Runs on every form with or without a CAPTCHA, and costs visitors nothing.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="settings-grid-full switch-row !cursor-default">
        <input type="checkbox" class="switch" checked disabled aria-label="Honeypot, always on">
        <span>
          <strong>Honeypot field — always on</strong>
          <span>A hidden field people never see. Anything that fills it in is a bot.</span>
        </span>
      </div>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="spam.time_trap" :disabled="!can.update">
        <span>
          <strong>Time trap</strong>
          <span>Drops forms sent faster than a person could fill them in.</span>
        </span>
      </label>
      <div class="field" x-show="spam.time_trap">
        <label for="spam-min-seconds">Minimum seconds on the page</label>
        <input id="spam-min-seconds" type="number" min="1" max="30" class="input" :class="fieldError('min_seconds') && 'input-invalid'"
               x-model.number="spam.min_seconds" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('min_seconds')" x-text="fieldError('min_seconds')"></p>
      </div>

      <label class="switch-row">
        <input type="checkbox" class="switch" x-model="spam.limit_links" :disabled="!can.update">
        <span>
          <strong>Limit links</strong>
          <span>Spam is mostly links. Real messages rarely need more than one or two.</span>
        </span>
      </label>
      <div class="field" x-show="spam.limit_links">
        <label for="spam-max-links">Most links allowed in one submission</label>
        <input id="spam-max-links" type="number" min="0" max="20" class="input" :class="fieldError('max_links') && 'input-invalid'"
               x-model.number="spam.max_links" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('max_links')" x-text="fieldError('max_links')"></p>
      </div>

      <div class="field">
        <label for="spam-rate">Submissions per minute from one visitor</label>
        <input id="spam-rate" type="number" min="1" max="120" class="input" :class="fieldError('rate_limit') && 'input-invalid'"
               x-model.number="spam.rate_limit" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('rate_limit')" x-text="fieldError('rate_limit')"></p>
      </div>
    </div>
  </section>

  {{-- rules --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Blocked words &amp; addresses</h3>
      <p>One per line. A submission that matches is dropped quietly.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="spam-words">Blocked words or phrases</label>
        <textarea id="spam-words" class="input !min-h-[130px] font-mono !text-[13px]" x-model="spam.blocked_words"
                  placeholder="casino&#10;crypto giveaway&#10;seo services" :disabled="!can.update"></textarea>
        <p class="mt-1 text-[11px] text-neutral-500">
          <span x-text="lineCount(spam.blocked_words)"></span> rule(s). Whole words only — "sex" does not catch "Essex". Not case-sensitive.
        </p>
      </div>
      <div class="field">
        <label for="spam-emails">Blocked email addresses or domains</label>
        <textarea id="spam-emails" class="input !min-h-[130px] font-mono !text-[13px]" :class="fieldError('blocked_emails') && 'input-invalid'"
                  x-model="spam.blocked_emails" placeholder="spammer@example.com&#10;@throwaway-mail.com" :disabled="!can.update"></textarea>
        <p class="field-error" x-show="fieldError('blocked_emails')" x-text="fieldError('blocked_emails')"></p>
        <p class="mt-1 text-[11px] text-neutral-500">
          <span x-text="lineCount(spam.blocked_emails)"></span> rule(s). Start with @ to block a whole domain.
        </p>
      </div>
    </div>
  </section>

  <div class="settings-savebar" x-show="can.update">
    <span class="counter" x-show="dirty">Unsaved changes</span>
    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
    <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || !dirty"
            x-text="saving ? 'Saving…' : 'Save spam protection'"></button>
  </div>
</form>
