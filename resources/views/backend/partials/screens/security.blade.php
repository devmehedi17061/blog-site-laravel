<template x-if="$store.ui.screen === 'security'">
<div x-data="security" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Access</div>
      <h2>Security</h2>
      <div class="page-subtitle">Who can sign in, how, from where — and a record of every attempt</div>
    </div>
  </div>

  <div class="settings-tabs" role="tablist">
    <template x-for="item in tabs" :key="item.key">
      <button type="button" role="tab" class="settings-tab" :class="tab === item.key && 'is-active'"
              :aria-selected="tab === item.key" @click="openTab(item.key)" x-text="item.label"></button>
    </template>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading security…</div>

  <template x-if="!loading && data && !can">
    <div class="role-note mb-4">You can look at the security settings but not change them.</div>
  </template>

  <template x-if="!loading && data">
    <div class="settings-stack">

      {{-- ═══ sign-in & passwords ═══ --}}
      <template x-if="tab === 'signin'">
        <div class="settings-stack">
          <section class="panel">
            <div class="settings-panel-head">
              <h3>Failed sign-ins</h3>
              <p>After too many wrong passwords, that email is paused from that address — guessing stops, and the real owner elsewhere is not locked out.</p>
            </div>
            <div class="settings-grid p-5">
              <div class="field">
                <label for="sec-attempts">Wrong passwords allowed</label>
                <input id="sec-attempts" type="number" min="3" max="20" class="input" :class="fieldError('max_attempts') && 'input-invalid'" x-model.number="form.max_attempts" :disabled="!can">
                <p class="field-error" x-show="fieldError('max_attempts')" x-text="fieldError('max_attempts')"></p>
              </div>
              <div class="field">
                <label for="sec-lockout">Then pause for (minutes)</label>
                <input id="sec-lockout" type="number" min="1" max="1440" class="input" :class="fieldError('lockout_minutes') && 'input-invalid'" x-model.number="form.lockout_minutes" :disabled="!can">
                <p class="field-error" x-show="fieldError('lockout_minutes')" x-text="fieldError('lockout_minutes')"></p>
              </div>
              <p class="settings-grid-full seo-hint">CAPTCHA on the sign-in page lives under <a href="#/settings/spam" class="text-accent-700 underline">Settings → Spam protection</a>.</p>
            </div>
          </section>

          <section class="panel">
            <div class="settings-panel-head">
              <h3>Password rules</h3>
              <p>Checked whenever a password is set — new accounts, resets, and people changing their own. Existing passwords keep working.</p>
            </div>
            <div class="settings-grid settings-grid-pairs p-5">
              <div class="field">
                <label for="sec-pw-min">Minimum length</label>
                <input id="sec-pw-min" type="number" min="8" max="64" class="input" :class="fieldError('password_min') && 'input-invalid'" x-model.number="form.password_min" :disabled="!can">
                <p class="field-error" x-show="fieldError('password_min')" x-text="fieldError('password_min')"></p>
              </div>
              <div class="field">
                <label for="sec-pw-expiry">Expire passwords after (days)</label>
                <input id="sec-pw-expiry" type="number" min="0" max="730" class="input" :class="fieldError('password_expiry_days') && 'input-invalid'" x-model.number="form.password_expiry_days" :disabled="!can">
                <p class="seo-hint">0 = never. Current guidance (NIST) is to not force changes — a long, unique password plus two-factor is stronger.</p>
                <p class="field-error" x-show="fieldError('password_expiry_days')" x-text="fieldError('password_expiry_days')"></p>
              </div>
              <template x-for="opt in [
                { key: 'password_mixed_case', label: 'Upper and lower case letters', note: 'At least one of each.' },
                { key: 'password_numbers', label: 'At least one number', note: '' },
                { key: 'password_symbols', label: 'At least one symbol', note: 'Such as ! ? # @.' },
                { key: 'password_uncompromised', label: 'Refuse leaked passwords', note: 'Checked against Have I Been Pwned. The password itself is never sent — only the first 5 characters of its hash.' },
              ]" :key="opt.key">
                <label class="switch-row" :data-rule="opt.key">
                  <input type="checkbox" class="switch" x-model="form[opt.key]" :disabled="!can">
                  <span><strong x-text="opt.label"></strong><span x-text="opt.note" x-show="opt.note"></span></span>
                </label>
              </template>
            </div>
          </section>

          <section class="panel">
            <div class="settings-panel-head">
              <h3>Staying signed in</h3>
              <p>How long an unattended panel stays open, and whether one person may be signed in in more than one place.</p>
            </div>
            <div class="settings-grid p-5">
              <div class="field">
                <label for="sec-idle">Sign out after inactivity (minutes)</label>
                <input id="sec-idle" type="number" min="5" max="1440" class="input" :class="fieldError('idle_minutes') && 'input-invalid'" x-model.number="form.idle_minutes" :disabled="!can">
                <p class="seo-hint">"Keep me signed in" still lasts until the person signs out.</p>
                <p class="field-error" x-show="fieldError('idle_minutes')" x-text="fieldError('idle_minutes')"></p>
              </div>
              <label class="switch-row">
                <input type="checkbox" class="switch" x-model="form.single_session" :disabled="!can">
                <span><strong>One session per person</strong><span>Signing in somewhere new signs that person out everywhere else.</span></span>
              </label>
            </div>
          </section>
        </div>
      </template>

      {{-- ═══ two-factor ═══ --}}
      <template x-if="tab === 'twofactor'">
        <div class="settings-stack">
          <section class="panel">
            <div class="settings-panel-head">
              <h3>Who must use two-factor</h3>
              <p>People in a ticked role are sent to My account to set it up the next time they use the panel, and cannot turn it off. Everyone else may turn it on for themselves.</p>
            </div>
            <div class="p-5">
              <div class="role-require-grid">
                <template x-for="role in data.roles" :key="role.id">
                  <label class="switch-row" :data-role="role.name">
                    <input type="checkbox" class="switch" :checked="requires(role.id)" @change="toggleRole(role.id)" :disabled="!can">
                    <span>
                      <strong x-text="role.name"></strong>
                      <span x-text="`${role.with_two_factor} of ${role.users} ${role.users === 1 ? 'person has' : 'people have'} it on`"></span>
                    </span>
                  </label>
                </template>
              </div>
              <p class="seo-hint mt-3" x-show="peopleStillToSetUp > 0" x-text="`${peopleStillToSetUp} person(s) in the ticked roles will be asked to set it up.`"></p>
              <p class="seo-hint mt-1" x-show="!data.email_codes_available">Email codes are unavailable until <a href="#/settings/email" class="text-accent-700 underline">Settings → Email</a> can send mail — people can still use an authenticator app.</p>
            </div>
          </section>

          <section class="panel">
            <div class="settings-panel-head">
              <h3>People</h3>
              <p>Who has two-factor on. Reset it for someone who lost their phone and their recovery codes.</p>
            </div>
            <div class="overflow-x-auto">
              <table class="data-table min-w-[640px]">
                <thead><tr><th>Person</th><th>Role</th><th>Two-factor</th><th>Password changed</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                  <template x-for="p in data.people" :key="p.id">
                    <tr :data-person="p.email">
                      <td><span class="cell-strong" x-text="p.name"></span><span class="block text-[12px] text-neutral-600" x-text="p.email"></span></td>
                      <td class="cell-muted" x-text="p.role ?? '—'"></td>
                      <td>
                        <span class="tag" :class="p.two_factor ? 'tag-success' : (form.two_factor_roles.includes(p.role_id) ? 'tag-accent-2' : 'tag-neutral')"
                              x-text="p.two_factor ? (p.two_factor === 'app' ? 'App' : 'Email') : (form.two_factor_roles.includes(p.role_id) ? 'Required — not set up' : 'Off')"></span>
                      </td>
                      <td class="cell-muted">
                        <span x-text="p.password_changed ?? '—'"></span>
                        <span class="ml-1 text-[11px] font-semibold text-accent-700" x-show="p.password_expired">expired</span>
                      </td>
                      <td class="text-right">
                        <button type="button" class="btn btn-ghost btn-sm text-accent-700" x-show="p.two_factor && p.can_manage" @click="resetTwoFactor(p)">Reset two-factor</button>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </template>

      {{-- ═══ sessions ═══ --}}
      <template x-if="tab === 'sessions'">
        <section class="panel">
          <div class="settings-panel-head">
            <h3>Signed in right now</h3>
            <p x-show="data.sessions_available">Every open panel session. Sign out one, or everything a person has open.</p>
            <p x-show="!data.sessions_available">Sessions can only be listed when SESSION_DRIVER=database.</p>
          </div>
          <div class="session-list">
            <template x-for="s in data.sessions" :key="s.key">
              <div class="session-row" :data-session-user="s.user">
                <span class="session-device"><span x-text="s.user"></span> · <span class="font-normal" x-text="s.device"></span></span>
                <span class="session-meta" x-text="`${s.ip ?? 'unknown address'} · active ${s.last_active}`"></span>
                <span class="tag tag-success ml-auto" x-show="s.current">You, here</span>
                <button type="button" class="btn btn-ghost btn-sm ml-auto text-accent-700" x-show="!s.current && can" @click="endSession(s)">Sign out</button>
              </div>
            </template>
            <p class="p-5 text-[13px] text-neutral-600" x-show="data.sessions.length === 0">Nobody else is signed in.</p>
          </div>
          <div class="border-t border-line p-5" x-show="can">
            <div class="text-[13px] font-semibold text-ink mb-2">Sign someone out everywhere</div>
            <div class="flex flex-wrap gap-2">
              <template x-for="p in data.people.filter(p => p.can_manage)" :key="p.id">
                <button type="button" class="btn btn-secondary btn-sm" @click="signOutPerson(p)" x-text="p.name"></button>
              </template>
            </div>
          </div>
        </section>
      </template>

      {{-- ═══ IP rules ═══ --}}
      <template x-if="tab === 'ip'">
        <div class="settings-stack">
          <section class="panel">
            <div class="settings-panel-head">
              <h3>Admin IP rules</h3>
              <p>Only the admin panel and its sign-in page — the public site stays open to everyone. A block rule always wins; if there are allow rules, only those addresses get in.</p>
            </div>
            <div class="flex flex-col gap-4 p-5">
              <label class="switch-row" :class="form.ip_rules_enabled && 'is-warning'">
                <input type="checkbox" class="switch" x-model="form.ip_rules_enabled" :disabled="!can">
                <span>
                  <strong>Enforce these rules</strong>
                  <span>Off by default. You cannot switch it on in a way that locks you out — and from the server, <code>php artisan security:unlock</code> switches it off.</span>
                </span>
              </label>
              <p class="field-error" x-show="fieldError('ip_rules_enabled')" x-text="fieldError('ip_rules_enabled')"></p>

              <p class="text-[13px] text-neutral-700">
                Your address: <code class="secret-key" x-text="data.you.ip"></code>
                <span class="tag ml-1" :class="youAreAllowed ? 'tag-success' : 'tag-accent'" x-text="youAreAllowed ? 'allowed by these rules' : 'NOT allowed by these rules'"></span>
              </p>
            </div>

            <div class="overflow-x-auto">
              <table class="data-table min-w-[560px]">
                <thead><tr><th>Address or range</th><th>Rule</th><th>Note</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                  <template x-for="rule in data.ip_rules" :key="rule.id">
                    <tr :data-rule-value="rule.value">
                      <td class="font-mono text-[13px]"><span x-text="rule.value"></span> <span class="ml-1 text-[10px] font-semibold text-accent-700" x-show="rule.matches_you">you</span></td>
                      <td><span class="tag" :class="rule.type === 'allow' ? 'tag-success' : 'tag-accent'" x-text="rule.type === 'allow' ? 'Allow' : 'Block'"></span></td>
                      <td class="cell-muted" x-text="rule.note ?? ''"></td>
                      <td class="text-right"><button type="button" class="btn btn-ghost btn-sm text-accent-700" x-show="can" @click="removeRule(rule)">Remove</button></td>
                    </tr>
                  </template>
                </tbody>
              </table>
              <p class="p-5 text-[13px] text-neutral-600" x-show="data.ip_rules.length === 0">No rules yet.</p>
            </div>

            <form class="flex flex-wrap items-start gap-3 border-t border-line p-5" x-show="can" @submit.prevent="addRule()">
              <div class="field min-w-[200px] flex-1">
                <label for="ip-value">Address or range</label>
                <input id="ip-value" class="input font-mono" :class="ruleErrors.value && 'input-invalid'" x-model.trim="newRule.value" placeholder="203.0.113.7 or 203.0.113.0/24">
                <p class="field-error" x-show="ruleErrors.value" x-text="ruleErrors.value?.[0]"></p>
              </div>
              <div class="field">
                <label for="ip-type">Rule</label>
                <select id="ip-type" class="input" x-model="newRule.type">
                  <option value="allow">Allow</option>
                  <option value="block">Block</option>
                </select>
              </div>
              <div class="field min-w-[160px] flex-1">
                <label for="ip-note">Note</label>
                <input id="ip-note" class="input" x-model="newRule.note" placeholder="Office, home, VPN…">
              </div>
              <div class="flex gap-2 self-end">
                <button type="button" class="btn btn-secondary" @click="useMyIp()">Use my address</button>
                <button type="submit" class="btn btn-primary">Add rule</button>
              </div>
            </form>
          </section>
        </div>
      </template>

      {{-- ═══ HTTPS & headers ═══ --}}
      <template x-if="tab === 'https'">
        <section class="panel">
          <div class="settings-panel-head">
            <h3>HTTPS &amp; security headers</h3>
            <p>
              You are on <strong x-text="data.you.host"></strong> over <strong x-text="data.you.secure ? 'HTTPS' : 'plain HTTP'"></strong>.
              <span x-show="data.you.local">This is a development address, so HTTPS is never forced here — these take effect on the live site.</span>
            </p>
          </div>
          <div class="settings-grid settings-grid-pairs p-5">
            <label class="switch-row">
              <input type="checkbox" class="switch" x-model="form.force_https" :disabled="!can">
              <span><strong>Always use HTTPS</strong><span>Plain http:// visits are redirected to https://. Needs an SSL certificate on the live server first.</span></span>
            </label>
            <label class="switch-row">
              <input type="checkbox" class="switch" x-model="form.hsts" :disabled="!can">
              <span><strong>HSTS</strong><span>Tells browsers to use HTTPS for a year without asking. Only turn on once HTTPS works everywhere — it cannot be undone quickly.</span></span>
            </label>
            <p class="settings-grid-full field-error" x-show="fieldError('hsts')" x-text="fieldError('hsts')"></p>
            <label class="switch-row settings-grid-full">
              <input type="checkbox" class="switch" x-model="form.headers" :disabled="!can">
              <span><strong>Protective headers</strong><span>Stops other sites framing yours (clickjacking), stops browsers guessing file types, keeps full addresses private when visitors follow links out, and turns off camera, microphone and location access.</span></span>
            </label>
          </div>
        </section>
      </template>

      {{-- ═══ log ═══ --}}
      <template x-if="tab === 'log'">
        <section class="panel">
          <div class="toolbar">
            <div class="chip-group">
              <template x-for="t in [['all', 'All'], ['sign-ins', 'Sign-ins'], ['failures', 'Failed & locked'], ['two-factor', 'Two-factor'], ['changes', 'Changes']]" :key="t[0]">
                <button type="button" class="chip" :class="log.type === t[0] && 'chip-active'" @click="setLogType(t[0])" x-text="t[1]"></button>
              </template>
            </div>
            <div class="relative ml-auto flex-[0_1_240px] min-w-[160px]">
              <input class="input !h-[30px] !text-xs" placeholder="Search email, address…" x-model="log.search" @input="searchLog()">
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="data-table min-w-[720px]">
              <thead><tr><th>When</th><th>What</th><th>Details</th><th>Who</th><th>Address</th></tr></thead>
              <tbody>
                <template x-for="row in log.rows" :key="row.id">
                  <tr :class="row.tone === 'bad' && 'log-bad'">
                    <td class="cell-muted cell-nowrap" :title="row.at" x-text="row.when"></td>
                    <td class="cell-strong cell-nowrap" x-text="row.action"></td>
                    <td class="text-[13px]" x-text="row.description"></td>
                    <td class="cell-muted" x-text="row.user ?? '—'"></td>
                    <td class="font-mono text-[12px] text-neutral-600" x-text="row.ip ?? ''"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <div x-show="log.loading" class="table-empty">Loading…</div>
          <div x-show="!log.loading && log.rows.length === 0" class="table-empty">Nothing recorded yet.</div>
          <div class="pagination-bar">
            <span class="pagination-count" x-text="`${log.meta.total} event(s)`"></span>
            <button class="page-btn" @click="loadLog(log.meta.current_page - 1)" :disabled="log.meta.current_page <= 1">Prev</button>
            <span class="px-2 text-[12px] text-neutral-600" x-text="`${log.meta.current_page} / ${log.meta.last_page}`"></span>
            <button class="page-btn" @click="loadLog(log.meta.current_page + 1)" :disabled="log.meta.current_page >= log.meta.last_page">Next</button>
          </div>
        </section>
      </template>

      {{-- save bar for the settings tabs --}}
      <div class="settings-savebar" x-show="can && savesHere">
        <span class="counter" x-show="dirty">Unsaved changes</span>
        <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
        <button type="button" class="btn btn-primary ml-auto" :disabled="saving || !dirty" @click="save()"
                x-text="saving ? 'Saving…' : 'Save security settings'"></button>
      </div>
    </div>
  </template>
</div>
</template>
