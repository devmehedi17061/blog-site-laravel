<template x-if="$store.ui.screen === 'account'">
<div x-data="account" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Signed in as <span x-text="data?.account.role ?? ''"></span></div>
      <h2>My account</h2>
      <div class="page-subtitle">Your details, your password, two-factor sign-in and where you are signed in</div>
    </div>
  </div>

  {{-- what this account owes before the panel opens --}}
  <template x-if="owes === 'two_factor_required'">
    <div class="owes-banner" role="alert">
      <strong>Two-factor sign-in is required for your role.</strong>
      Set it up below — the rest of the panel opens as soon as it is on.
    </div>
  </template>
  <template x-if="owes === 'password_expired'">
    <div class="owes-banner" role="alert">
      <strong>Your password has expired.</strong>
      Choose a new one below — the rest of the panel opens as soon as it is changed.
    </div>
  </template>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading your account…</div>

  <template x-if="!loading && data">
    <div class="settings-stack">

      {{-- two-factor first when it is what is owed --}}
      <section class="panel" :style="owes === 'two_factor_required' ? 'order:-1' : ''" id="account-2fa">
        <div class="settings-panel-head">
          <h3 class="flex items-center gap-2">
            Two-factor sign-in
            <span class="tag" :class="data.two_factor.enabled ? 'tag-success' : 'tag-neutral'"
                  x-text="data.two_factor.enabled ? 'On' : 'Off'"></span>
            <span class="tag tag-accent-2" x-show="data.two_factor.required">Required for your role</span>
          </h3>
          <p>A code on top of your password, so a stolen password alone cannot get into your account.</p>
        </div>

        <div class="flex flex-col gap-4 p-5">
          {{-- the recovery codes, shown once --}}
          <template x-if="recoveryCodes">
            <div class="recovery-box">
              <strong>Save these recovery codes now.</strong>
              <span>Each gets you in once if you lose your phone or cannot get the email. They will not be shown again.</span>
              <ol class="recovery-codes">
                <template x-for="code in recoveryCodes" :key="code">
                  <li x-text="code"></li>
                </template>
              </ol>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-secondary btn-sm" @click="copyCodes()">Copy</button>
                <button type="button" class="btn btn-secondary btn-sm" @click="downloadCodes()">Download .txt</button>
                <button type="button" class="btn btn-primary btn-sm ml-auto" @click="recoveryCodes = null">I have saved them</button>
              </div>
            </div>
          </template>

          {{-- off, and not setting up: choose a method --}}
          <template x-if="!data.two_factor.enabled && !setup">
            <div class="provider-grid" role="group" aria-label="Choose a two-factor method">
              <button type="button" class="provider-card" data-method="app" @click="startSetup('app')">
                <span class="provider-mono">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20"></rect><path d="M11 18h2"></path></svg>
                </span>
                <span class="provider-label">Authenticator app <span class="font-normal text-accent-700">· recommended</span></span>
                <span class="provider-summary">Google Authenticator, Microsoft Authenticator, Authy… Works offline.</span>
              </button>
              <button type="button" class="provider-card" data-method="email" @click="startSetup('email')" :disabled="!data.two_factor.email_available">
                <span class="provider-mono">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14"></rect><path d="m3 7 9 6 9-6"></path></svg>
                </span>
                <span class="provider-label">Email code</span>
                <span class="provider-summary" x-text="data.two_factor.email_available ? `A code to ${data.account.email} each time you sign in.` : 'Needs Settings → Email to be set up first.'"></span>
              </button>
            </div>
          </template>

          {{-- setting up --}}
          <template x-if="setup">
            <div class="twofa-setup">
              <template x-if="setup.method === 'app'">
                <div class="flex flex-wrap items-start gap-5">
                  <div class="qr-box">
                    <img x-show="setup.qr" :src="setup.qr" alt="QR code for your authenticator app" width="200" height="200">
                    <span x-show="!setup.qr" class="text-[12px] text-neutral-500">Making the code…</span>
                  </div>
                  <ol class="twofa-steps">
                    <li>Open your authenticator app and add an account — usually a <strong>+</strong> button.</li>
                    <li>Scan this QR code. No camera? Type this key instead:
                      <code class="secret-key" x-text="setup.secret"></code></li>
                    <li>Enter the 6-digit code the app now shows.</li>
                  </ol>
                </div>
              </template>

              <template x-if="setup.method === 'email'">
                <p class="text-[13px] text-neutral-700" x-text="setup.message || 'Sending the code…'"></p>
              </template>

              <form class="flex flex-wrap items-start gap-3" @submit.prevent="confirmSetup()">
                <div class="field">
                  <label for="twofa-code" class="sr-only">Code</label>
                  <input id="twofa-code" class="input !w-[170px] text-center font-mono !text-[20px] tracking-[0.3em]" :class="setup.error && 'input-invalid'"
                         x-model="setup.code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" placeholder="123456">
                </div>
                <button type="submit" class="btn btn-primary" :disabled="setup.busy || setup.code.replace(/\s/g, '').length !== 6"
                        x-text="setup.busy ? 'Checking…' : 'Turn on'"></button>
                <button type="button" class="btn btn-ghost" @click="cancelSetup()">Cancel</button>
                <p class="field-error w-full" x-show="setup.error" x-text="setup.error"></p>
              </form>
            </div>
          </template>

          {{-- on --}}
          <template x-if="data.two_factor.enabled && !setup">
            <div class="flex flex-col gap-4">
              <p class="text-[13px] text-neutral-700">
                Using <strong x-text="data.two_factor.method === 'app' ? 'an authenticator app' : 'email codes'"></strong>
                since <span x-text="data.two_factor.since"></span>.
                <span :class="data.two_factor.recovery_left <= 2 && 'font-semibold text-accent-700'"
                      x-text="`${data.two_factor.recovery_left} recovery code(s) left.`"></span>
              </p>

              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-secondary btn-sm" @click="startSetup(data.two_factor.method === 'app' ? 'email' : 'app')"
                        :disabled="data.two_factor.method === 'app' && !data.two_factor.email_available"
                        x-text="data.two_factor.method === 'app' ? 'Switch to email codes' : 'Switch to an authenticator app'"></button>
              </div>

              <div class="danger-zone">
                <div class="field">
                  <label for="twofa-password">Current password <span class="font-normal text-neutral-500">— to change your recovery codes or turn two-factor off</span></label>
                  <input id="twofa-password" type="password" class="input max-w-[320px]" x-model="dangerPassword" autocomplete="current-password">
                  <p class="field-error" x-show="dangerError" x-text="dangerError"></p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button type="button" class="btn btn-secondary btn-sm" @click="newRecoveryCodes()" :disabled="!dangerPassword">New recovery codes</button>
                  <button type="button" class="btn btn-ghost btn-sm text-accent-700" @click="turnOff()" :disabled="!dangerPassword || data.two_factor.required"
                          :title="data.two_factor.required ? 'Your role requires two-factor' : ''">Turn off two-factor</button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </section>

      {{-- password --}}
      <section class="panel" :style="owes === 'password_expired' ? 'order:-1' : ''">
        <div class="settings-panel-head">
          <h3>Password</h3>
          <p>Last changed <span x-text="data.account.password_changed ?? 'a while ago'"></span>. Changing it signs you out everywhere else.</p>
        </div>
        <form class="settings-grid p-5" @submit.prevent="savePassword()" autocomplete="off">
          <div class="field">
            <label for="pw-current">Current password</label>
            <input id="pw-current" :type="showPasswords ? 'text' : 'password'" class="input" :class="passwordErrors.current_password && 'input-invalid'"
                   x-model="password.current_password" autocomplete="current-password">
            <p class="field-error" x-show="passwordErrors.current_password" x-text="passwordErrors.current_password?.[0]"></p>
          </div>
          <div class="field">
            <label for="pw-new">New password</label>
            <input id="pw-new" :type="showPasswords ? 'text' : 'password'" class="input" :class="passwordErrors.password && 'input-invalid'"
                   x-model="password.password" autocomplete="new-password">
            <p class="seo-hint" x-text="data.account.password_hint"></p>
            <template x-for="message in (passwordErrors.password ?? [])" :key="message">
              <p class="field-error" x-text="message"></p>
            </template>
          </div>
          <div class="field">
            <label for="pw-confirm">New password again</label>
            <input id="pw-confirm" :type="showPasswords ? 'text' : 'password'" class="input" x-model="password.password_confirmation" autocomplete="new-password">
          </div>
          <div class="settings-grid-full flex flex-wrap items-center gap-3">
            <label class="flex items-center gap-2 text-[13px] text-neutral-700">
              <input type="checkbox" class="h-4 w-4 accent-[var(--color-accent)]" x-model="showPasswords"> Show passwords
            </label>
            <button type="submit" class="btn btn-primary ml-auto" :disabled="passwordSaving || !password.current_password || !password.password"
                    x-text="passwordSaving ? 'Saving…' : 'Change password'"></button>
          </div>
        </form>
      </section>

      {{-- profile --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Profile</h3>
          <p>Your name as it appears on posts and in the panel, and the email you sign in with.</p>
        </div>
        <form class="settings-grid p-5" @submit.prevent="saveProfile()" autocomplete="off">
          <div class="field">
            <label for="profile-name">Name</label>
            <input id="profile-name" class="input" :class="profileErrors.name && 'input-invalid'" x-model="profile.name">
            <p class="field-error" x-show="profileErrors.name" x-text="profileErrors.name?.[0]"></p>
          </div>
          <div class="field">
            <label for="profile-email">Email</label>
            <input id="profile-email" type="email" class="input" :class="profileErrors.email && 'input-invalid'" x-model="profile.email">
            <p class="field-error" x-show="profileErrors.email" x-text="profileErrors.email?.[0]"></p>
          </div>
          <div class="field" x-show="emailChanged">
            <label for="profile-password">Current password <span class="font-normal text-neutral-500">— needed to change your email</span></label>
            <input id="profile-password" type="password" class="input" :class="profileErrors.current_password && 'input-invalid'"
                   x-model="profile.current_password" autocomplete="current-password">
            <p class="field-error" x-show="profileErrors.current_password" x-text="profileErrors.current_password?.[0]"></p>
          </div>
          <div class="settings-grid-full flex">
            <button type="submit" class="btn btn-primary ml-auto" :disabled="profileSaving" x-text="profileSaving ? 'Saving…' : 'Save profile'"></button>
          </div>
        </form>
      </section>

      {{-- sessions --}}
      <section class="panel">
        <div class="settings-panel-head flex flex-wrap items-start gap-3">
          <div class="flex-1">
            <h3>Where you are signed in</h3>
            <p>Anything you do not recognise, sign out — then change your password.</p>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" @click="endOthers()" x-show="data.sessions.length > 1">Sign out other devices</button>
        </div>
        <div class="session-list">
          <template x-for="s in data.sessions" :key="s.key">
            <div class="session-row">
              <span class="session-device" x-text="s.device"></span>
              <span class="session-meta" x-text="`${s.ip ?? 'unknown address'} · active ${s.last_active}`"></span>
              <span class="tag tag-success ml-auto" x-show="s.current">This browser</span>
              <button type="button" class="btn btn-ghost btn-sm ml-auto text-accent-700" x-show="!s.current" @click="endSession(s)">Sign out</button>
            </div>
          </template>
          <p class="p-5 text-[13px] text-neutral-600" x-show="data.sessions.length === 0">No sessions to show.</p>
        </div>
      </section>
    </div>
  </template>
</div>
</template>
