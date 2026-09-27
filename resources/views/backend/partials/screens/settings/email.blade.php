{{-- Settings → Email. One root element: it sits inside an x-if template. --}}
<form class="settings-stack" @submit.prevent="saveMail()" autocomplete="off">

  {{-- 1. which service --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>How mail is sent</h3>
      <p>Form notifications and every other email the site sends go out through this account.</p>
    </div>
    <div class="p-5">
      <div class="provider-grid" role="radiogroup" aria-label="Mail provider">
        <template x-for="p in providers" :key="p.value">
          <button type="button" role="radio" class="provider-card" :aria-checked="mail.provider === p.value"
                  :class="mail.provider === p.value && 'is-active'" :disabled="!can.update"
                  :data-provider="p.value" @click="chooseProvider(p.value)">
            <span class="provider-mono" x-text="p.mono"></span>
            <span class="provider-label" x-text="p.label"></span>
            <span class="provider-summary" x-text="p.summary"></span>
          </button>
        </template>
      </div>

      <template x-if="mail.provider === 'log'">
        <div class="settings-callout mt-4">
          <strong>Email is off.</strong> Form notifications are written to <span class="font-mono">storage/logs/laravel.log</span>
          instead of being delivered. Pick a provider above to send real mail.
        </div>
      </template>
    </div>
  </section>

  {{-- 2. the chosen provider's connection --}}
  <template x-if="mail.provider !== 'log'">
    <section class="panel">
      <div class="settings-panel-head">
        <h3 x-text="`${provider.label} connection`"></h3>
        <p x-show="mail.provider === 'gmail'">Sends through smtp.gmail.com on port 587 (TLS).</p>
        <p x-show="mail.provider === 'zoho'">Pick where your Zoho account lives and what kind it is — the server follows from that.</p>
        <p x-show="mail.provider === 'webmail'">The mailbox your web host gave you. The details are in your hosting panel, usually under Email Accounts → Connect Devices.</p>
        <p x-show="mail.provider === 'smtp'">Any SMTP server. Your provider's help pages list these values.</p>
      </div>

      <div class="settings-grid p-5">

        {{-- Gmail --}}
        <template x-if="mail.provider === 'gmail'">
          <div class="settings-grid-full settings-steps">
            <strong>Before you start:</strong> Google does not accept your normal password here.
            <ol>
              <li>Turn on <strong>2-Step Verification</strong> for the Google account.</li>
              <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords ↗</a>, create one called "Website" and copy the 16 letters.</li>
              <li>Paste them below. Spaces are fine.</li>
            </ol>
          </div>
        </template>
        <template x-if="mail.provider === 'gmail'">
          <div class="field">
            <label for="mail-gmail-address">Gmail address</label>
            <input id="mail-gmail-address" type="email" class="input" :class="fieldError('gmail_address') && 'input-invalid'"
                   x-model="mail.gmail_address" placeholder="you@gmail.com" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('gmail_address')" x-text="fieldError('gmail_address')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'gmail'">
          <div class="field">
            <label for="mail-gmail-password">App password</label>
            <input id="mail-gmail-password" type="password" class="input font-mono" :class="fieldError('gmail_app_password') && 'input-invalid'"
                   x-model="mail.gmail_app_password" :placeholder="passwordPlaceholder('gmail_app_password') || 'abcd efgh ijkl mnop'"
                   autocomplete="new-password" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('gmail_app_password')" x-text="fieldError('gmail_app_password')"></p>
          </div>
        </template>

        {{-- Zoho --}}
        <template x-if="mail.provider === 'zoho'">
          <div class="field">
            <label for="mail-zoho-region">Data centre</label>
            <select id="mail-zoho-region" class="input" x-model="mail.zoho_region" :disabled="!can.update">
              <template x-for="r in options.zoho_regions" :key="r.value">
                <option :value="r.value" x-text="r.label" :selected="r.value === mail.zoho_region"></option>
              </template>
            </select>
            <p class="mt-1 text-[11px] text-neutral-500">The address you sign in to Zoho at — zoho.com, zoho.eu, zoho.in…</p>
          </div>
        </template>
        <template x-if="mail.provider === 'zoho'">
          <div class="field">
            <label>Account type</label>
            <div class="segmented">
              <label :class="mail.zoho_account === 'personal' && 'is-active'">
                <input type="radio" value="personal" x-model="mail.zoho_account" :disabled="!can.update"> Personal (free)
              </label>
              <label :class="mail.zoho_account === 'organization' && 'is-active'">
                <input type="radio" value="organization" x-model="mail.zoho_account" :disabled="!can.update"> Your domain (paid)
              </label>
            </div>
            <p class="mt-1 text-[11px] text-neutral-500">Server: <span class="font-mono" x-text="zohoServer"></span></p>
          </div>
        </template>
        <template x-if="mail.provider === 'zoho'">
          <div class="field">
            <label for="mail-zoho-address">Zoho address</label>
            <input id="mail-zoho-address" type="email" class="input" :class="fieldError('zoho_address') && 'input-invalid'"
                   x-model="mail.zoho_address" placeholder="you@yourdomain.com" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('zoho_address')" x-text="fieldError('zoho_address')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'zoho'">
          <div class="field">
            <label for="mail-zoho-password">Password <span class="font-normal text-neutral-500">— or an app-specific password if two-factor sign-in is on</span></label>
            <input id="mail-zoho-password" type="password" class="input" :class="fieldError('zoho_password') && 'input-invalid'"
                   x-model="mail.zoho_password" :placeholder="passwordPlaceholder('zoho_password')" autocomplete="new-password" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('zoho_password')" x-text="fieldError('zoho_password')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'zoho'">
          <div class="field">
            <label>Security</label>
            <div class="segmented">
              <label :class="mail.zoho_security === 'ssl' && 'is-active'">
                <input type="radio" value="ssl" x-model="mail.zoho_security" :disabled="!can.update"> SSL · 465
              </label>
              <label :class="mail.zoho_security === 'tls' && 'is-active'">
                <input type="radio" value="tls" x-model="mail.zoho_security" :disabled="!can.update"> TLS · 587
              </label>
            </div>
          </div>
        </template>

        {{-- Webmail --}}
        <template x-if="mail.provider === 'webmail'">
          <div class="field">
            <label for="mail-webmail-host">Outgoing mail server</label>
            <input id="mail-webmail-host" class="input font-mono" :class="fieldError('webmail_host') && 'input-invalid'"
                   x-model="mail.webmail_host" placeholder="mail.yourdomain.com" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('webmail_host')" x-text="fieldError('webmail_host')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'webmail'">
          <div class="field">
            <label>Port</label>
            <div class="segmented">
              <label :class="Number(mail.webmail_port) === 465 && 'is-active'">
                <input type="radio" :value="465" x-model.number="mail.webmail_port" :disabled="!can.update"> 465 · SSL (recommended)
              </label>
              <label :class="Number(mail.webmail_port) === 587 && 'is-active'">
                <input type="radio" :value="587" x-model.number="mail.webmail_port" :disabled="!can.update"> 587 · TLS
              </label>
            </div>
          </div>
        </template>
        <template x-if="mail.provider === 'webmail'">
          <div class="field">
            <label for="mail-webmail-username">Username <span class="font-normal text-neutral-500">— usually the full email address</span></label>
            <input id="mail-webmail-username" class="input" :class="fieldError('webmail_username') && 'input-invalid'"
                   x-model="mail.webmail_username" placeholder="hello@yourdomain.com" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('webmail_username')" x-text="fieldError('webmail_username')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'webmail'">
          <div class="field">
            <label for="mail-webmail-password">Mailbox password</label>
            <input id="mail-webmail-password" type="password" class="input" :class="fieldError('webmail_password') && 'input-invalid'"
                   x-model="mail.webmail_password" :placeholder="passwordPlaceholder('webmail_password')" autocomplete="new-password" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('webmail_password')" x-text="fieldError('webmail_password')"></p>
          </div>
        </template>

        {{-- Custom SMTP --}}
        <template x-if="mail.provider === 'smtp'">
          <div class="field">
            <label for="mail-smtp-host">SMTP server</label>
            <input id="mail-smtp-host" class="input font-mono" :class="fieldError('smtp_host') && 'input-invalid'"
                   x-model="mail.smtp_host" placeholder="smtp.example.com" :disabled="!can.update">
            <p class="field-error" x-show="fieldError('smtp_host')" x-text="fieldError('smtp_host')"></p>
          </div>
        </template>
        <template x-if="mail.provider === 'smtp'">
          <div class="grid grid-cols-2 gap-3">
            <div class="field">
              <label for="mail-smtp-port">Port</label>
              <input id="mail-smtp-port" type="number" min="1" max="65535" class="input" :class="fieldError('smtp_port') && 'input-invalid'"
                     x-model.number="mail.smtp_port" :disabled="!can.update">
              <p class="field-error" x-show="fieldError('smtp_port')" x-text="fieldError('smtp_port')"></p>
            </div>
            <div class="field">
              <label for="mail-smtp-security">Security</label>
              <select id="mail-smtp-security" class="input" x-model="mail.smtp_security" :disabled="!can.update">
                <option value="tls" :selected="mail.smtp_security === 'tls'">TLS (STARTTLS)</option>
                <option value="ssl" :selected="mail.smtp_security === 'ssl'">SSL</option>
                <option value="none" :selected="mail.smtp_security === 'none'">None</option>
              </select>
            </div>
          </div>
        </template>
        <template x-if="mail.provider === 'smtp'">
          <div class="field">
            <label for="mail-smtp-username">Username <span class="font-normal text-neutral-500">— blank if the server needs no login</span></label>
            <input id="mail-smtp-username" class="input" x-model="mail.smtp_username" :disabled="!can.update">
          </div>
        </template>
        <template x-if="mail.provider === 'smtp'">
          <div class="field">
            <label for="mail-smtp-password">Password</label>
            <input id="mail-smtp-password" type="password" class="input" x-model="mail.smtp_password"
                   :placeholder="passwordPlaceholder('smtp_password')" autocomplete="new-password" :disabled="!can.update">
          </div>
        </template>
        <template x-if="mail.provider === 'smtp' && mail.smtp_security === 'none'">
          <div class="settings-grid-full settings-callout">
            With no encryption the password and every email cross the network in plain text. Use this only for a server on the same machine or private network.
          </div>
        </template>
      </div>
    </section>
  </template>

  {{-- 3. who it comes from --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Sender</h3>
      <p>What recipients see in their inbox.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="mail-from-name">From name</label>
        <input id="mail-from-name" class="input" x-model="mail.from_name" :placeholder="$store.auth.user ? 'Your site name' : ''" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('from_name')" x-text="fieldError('from_name')"></p>
      </div>
      <div class="field">
        <label for="mail-from-address">From address <span class="font-normal text-neutral-500">— blank uses the account's own</span></label>
        <input id="mail-from-address" type="email" class="input" :class="fieldError('from_address') && 'input-invalid'"
               x-model="mail.from_address" :placeholder="accountAddress || 'hello@yourdomain.com'" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('from_address')" x-text="fieldError('from_address')"></p>
        <p class="mt-1 text-[11px] text-neutral-500" x-show="['gmail', 'zoho'].includes(mail.provider)">
          <span x-text="provider.label"></span> only sends from the account's own address or an alias verified there.
        </p>
      </div>
      <div class="field">
        <label for="mail-reply-to">Reply-to address <span class="font-normal text-neutral-500">— optional</span></label>
        <input id="mail-reply-to" type="email" class="input" :class="fieldError('reply_to') && 'input-invalid'"
               x-model="mail.reply_to" placeholder="Where replies should go" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('reply_to')" x-text="fieldError('reply_to')"></p>
      </div>
    </div>
  </section>

  {{-- 4. try it --}}
  <section class="panel" x-show="can.update">
    <div class="settings-panel-head">
      <h3>Send a test email</h3>
      <p>Uses what is on screen, saved or not — so you can check a password before saving it.</p>
    </div>
    <div class="flex flex-wrap items-start gap-3 p-5">
      <div class="field min-w-[240px] flex-1">
        <label for="mail-test-to" class="sr-only">Send the test to</label>
        <input id="mail-test-to" type="email" class="input" :class="fieldError('test_to') && 'input-invalid'"
               x-model="testTo" placeholder="you@example.com">
        <p class="field-error" x-show="fieldError('test_to')" x-text="fieldError('test_to')"></p>
      </div>
      <button type="button" class="btn btn-secondary" @click="sendTest()" :disabled="testing"
              x-text="testing ? 'Sending…' : 'Send test email'"></button>

      <template x-if="testResult">
        <div class="test-result" :class="testResult.ok ? 'is-ok' : 'is-bad'" role="status" x-text="testResult.message"></div>
      </template>
    </div>
  </section>

  {{-- save --}}
  <div class="settings-savebar" x-show="can.update">
    <span class="counter" x-show="dirty">Unsaved changes</span>
    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
    <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || !dirty"
            x-text="saving ? 'Saving…' : 'Save email settings'"></button>
  </div>
</form>
