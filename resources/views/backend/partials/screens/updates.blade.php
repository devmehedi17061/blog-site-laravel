<template x-if="$store.ui.screen === 'updates'">
<div x-data="updates" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">System</div>
      <h2>Updates</h2>
      <div class="page-subtitle">The CMS core, from GitHub. An update changes the code only — never this site's pages, posts, media, settings or pack.</div>
    </div>
    <button type="button" class="btn btn-secondary" x-show="can" @click="check()" :disabled="!!busy">
      <span x-text="busy === 'check' ? 'Checking…' : 'Check now'"></span>
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading…</div>

  <template x-if="!loading && data">
    <div class="settings-stack">

      <template x-if="!can">
        <div class="role-note">Only a Super Admin can install updates or change these settings.</div>
      </template>

      {{-- ═══ the version ═══ --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Version</h3>
          <p>
            This site runs <b x-text="'v' + data.current"></b>
            <template x-if="data.site_pack"><span> with the site pack <b x-text="data.site_pack"></b></span></template>.
            Checked <span x-text="when(data.checked_at)"></span> against <span class="font-mono" x-text="data.repository"></span>.
          </p>
        </div>
        <div class="p-5">
          <template x-if="data.error">
            <div class="alert mb-4" x-text="'GitHub: ' + data.error"></div>
          </template>

          <template x-if="data.available">
            <div class="flex flex-col gap-3">
              <div class="flex flex-wrap items-center gap-3">
                <span class="chip chip-active" x-text="'v' + data.latest.version + ' is available'"></span>
                <span class="chip" x-show="data.latest.security">Security update</span>
                <a class="text-[13px] underline" :href="data.latest.url" target="_blank" rel="noopener">Release on GitHub ↗</a>
              </div>
              <pre class="max-h-64 overflow-auto whitespace-pre-wrap border border-line bg-muted p-3 text-[13px]" x-text="data.latest.notes || 'No notes.'"></pre>
              <div x-show="can">
                <button type="button" class="btn btn-primary" @click="install()" :disabled="!!busy || !data.zip_available">
                  <span x-text="busy === 'install' ? 'Updating — keep this tab open…' : 'Back up and update to v' + data.latest.version"></span>
                </button>
                <p class="mt-2 text-[12px] text-neutral-600" x-show="!data.zip_available">The server's PHP has no zip extension; ask the host to enable it.</p>
              </div>
            </div>
          </template>

          <template x-if="!data.available && !data.error">
            <p class="text-[14px]">✔ This is the newest version<span x-show="data.latest"> (<span x-text="'v' + (data.latest?.version ?? '')"></span> on GitHub)</span>.</p>
          </template>
        </div>
      </section>

      {{-- ═══ what the last update did ═══ --}}
      <template x-if="log.length">
        <section class="panel">
          <div class="settings-panel-head"><h3>Last update</h3></div>
          <pre class="m-5 max-h-72 overflow-auto whitespace-pre-wrap border border-line bg-muted p-3 font-mono text-[12px]" x-text="log.join('\n')"></pre>
        </section>
      </template>

      {{-- ═══ settings ═══ --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Install by itself</h3>
          <p>Checked once a day (by the scheduler, or when someone opens this panel on hosts without cron). A backup is taken before every update.</p>
        </div>
        <div class="flex flex-col gap-4 p-5">
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-2 text-[14px]"><input type="radio" value="none" x-model="form.auto" :disabled="!can"> Never — show the update here and wait</label>
            <label class="flex items-center gap-2 text-[14px]"><input type="radio" value="security" x-model="form.auto" :disabled="!can"> Security updates only</label>
            <label class="flex items-center gap-2 text-[14px]"><input type="radio" value="all" x-model="form.auto" :disabled="!can"> Every update</label>
          </div>
          <div class="field max-w-[520px]">
            <label for="upd-token">GitHub token <span class="text-neutral-500">(only for a private repository)</span></label>
            <input id="upd-token" type="password" class="input" autocomplete="off" x-model="form.token" :disabled="!can"
                   :placeholder="data.token_set ? 'Saved — type to replace' : 'github_pat_…'">
            <p class="text-[12px] text-neutral-600">A fine-grained token with <b>Contents: Read-only</b> on this repository. Stored encrypted.</p>
            <label class="flex items-center gap-2 text-[13px]" x-show="data.token_set"><input type="checkbox" x-model="form.token_clear" :disabled="!can"> Remove the saved token</label>
          </div>
          <div x-show="can"><button type="button" class="btn btn-primary" @click="save()" :disabled="!!busy">Save</button></div>
        </div>
      </section>

      {{-- ═══ backups ═══ --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Backups</h3>
          <p>Kept in storage/app/private — never reachable from the web. "Go back" restores the code saved before the last update.</p>
        </div>
        <div class="grid gap-5 p-5 md:grid-cols-2">
          <div>
            <h4 class="!mb-2 text-[13px]">Code (before each update)</h4>
            <template x-for="b in data.code_backups" :key="b.name">
              <div class="flex justify-between border-b border-line py-1.5 text-[13px]"><span class="font-mono" x-text="b.name"></span><span x-text="size(b.size)"></span></div>
            </template>
            <p class="text-[13px] text-neutral-600" x-show="!data.code_backups.length">None yet.</p>
            <button type="button" class="btn btn-secondary mt-3" x-show="can && data.code_backups.length" @click="rollback()" :disabled="!!busy">Go back to the previous version</button>
          </div>
          <div>
            <h4 class="!mb-2 text-[13px]">Database (daily)</h4>
            <template x-for="b in data.database_backups" :key="b.name">
              <div class="flex justify-between border-b border-line py-1.5 text-[13px]"><span class="font-mono" x-text="b.name"></span><span x-text="size(b.size)"></span></div>
            </template>
            <p class="text-[13px] text-neutral-600" x-show="!data.database_backups.length">None yet.</p>
          </div>
        </div>
      </section>
    </div>
  </template>
</div>
</template>
