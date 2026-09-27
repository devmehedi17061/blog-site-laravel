{{-- Settings → Redirects. One root element: it sits inside an x-if template. --}}
<div class="settings-stack">

  <section class="panel">
    <div class="settings-panel-head">
      <h3>Add a redirect</h3>
      <p>Send an old address somewhere new — links from a previous website, a page you retired. When a live post, page or category changes its address, a redirect is added for you.</p>
    </div>
    <form class="settings-grid p-5" @submit.prevent="addRedirect()" autocomplete="off" x-show="can.update">
      <div class="field">
        <label for="redirect-from">Old address</label>
        <input id="redirect-from" class="input font-mono" :class="redirects.errors.from && 'input-invalid'"
               x-model.trim="redirects.form.from" placeholder="/old-page">
        <p class="field-error" x-show="redirects.errors.from" x-text="redirects.errors.from?.[0]"></p>
      </div>
      <div class="field">
        <label for="redirect-to">Goes to</label>
        <input id="redirect-to" class="input font-mono" :class="redirects.errors.to && 'input-invalid'"
               x-model.trim="redirects.form.to" placeholder="/new-page or https://…">
        <p class="field-error" x-show="redirects.errors.to" x-text="redirects.errors.to?.[0]"></p>
      </div>
      <div class="field">
        <label for="redirect-status">Type</label>
        <select id="redirect-status" class="input" x-model.number="redirects.form.status">
          <option :value="301" :selected="redirects.form.status === 301">Permanent (301) — moved for good</option>
          <option :value="302" :selected="redirects.form.status === 302">Temporary (302) — coming back</option>
        </select>
      </div>
      <div class="field">
        <label for="redirect-note">Note <span class="font-normal text-neutral-500">— optional</span></label>
        <input id="redirect-note" class="input" x-model="redirects.form.note" placeholder="Why this redirect exists">
      </div>
      <div class="settings-grid-full flex">
        <button type="submit" class="btn btn-primary ml-auto" :disabled="redirects.saving || !redirects.form.from || !redirects.form.to"
                x-text="redirects.saving ? 'Adding…' : 'Add redirect'"></button>
      </div>
    </form>
  </section>

  <section class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="s in [['all', 'All'], ['auto', 'Automatic'], ['manual', 'Added by hand']]" :key="s[0]">
          <button type="button" class="chip" :class="redirects.source === s[0] && 'chip-active'" @click="setRedirectSource(s[0])" x-text="s[1]"></button>
        </template>
      </div>
      <div class="relative ml-auto flex-[0_1_240px] min-w-[160px]">
        <input class="input !h-[30px] !text-xs" placeholder="Search addresses…" x-model="redirects.search" @input="searchRedirects()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[720px]">
        <thead><tr><th>Old address</th><th>Goes to</th><th>Type</th><th>Used</th><th class="text-right">Actions</th></tr></thead>
        <tbody>
          <template x-for="row in redirects.rows" :key="row.id">
            <tr :data-redirect="row.from">
              <td class="font-mono text-[12px]">
                <a :href="row.from" target="_blank" rel="noopener" class="text-ink hover:text-accent-700" x-text="row.from"></a>
                <span class="block font-sans text-[11px] text-neutral-500" x-show="row.note" x-text="row.note"></span>
              </td>
              <td class="font-mono text-[12px] text-neutral-700" x-text="row.to"></td>
              <td>
                <span class="tag" :class="row.source === 'auto' ? 'tag-neutral' : 'tag-accent'" x-text="row.source === 'auto' ? 'Automatic' : 'By hand'"></span>
                <span class="ml-1 text-[11px] text-neutral-500" x-text="row.status"></span>
              </td>
              <td class="cell-muted cell-nowrap" x-text="row.hits ? `${row.hits}× · ${row.last_hit}` : 'Not yet'"></td>
              <td class="text-right">
                <button type="button" class="btn btn-ghost btn-sm text-accent-700" x-show="can.update" @click="removeRedirect(row)">Remove</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div x-show="redirects.loading" class="table-empty">Loading…</div>
    <div x-show="!redirects.loading && redirects.rows.length === 0" class="table-empty">No redirects yet. They appear here when an address changes.</div>
    <div class="pagination-bar">
      <span class="pagination-count" x-text="`${redirects.meta.total} redirect(s)`"></span>
      <button class="page-btn" @click="loadRedirects(redirects.meta.current_page - 1)" :disabled="redirects.meta.current_page <= 1">Prev</button>
      <span class="px-2 text-[12px] text-neutral-600" x-text="`${redirects.meta.current_page} / ${redirects.meta.last_page}`"></span>
      <button class="page-btn" @click="loadRedirects(redirects.meta.current_page + 1)" :disabled="redirects.meta.current_page >= redirects.meta.last_page">Next</button>
    </div>
  </section>
</div>
