<template x-if="$store.ui.screen === 'pages' && !$store.ui.editing">
<div x-data="pages" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Pages</h2>
      <div class="page-subtitle">Static pages assembled from reusable sections</div>
    </div>

    <button x-show="$store.auth.can('pages.create')" @click="openCreate()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M5 12h14"></path><path d="M12 5v14"></path>
      </svg>
      New page
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  {{-- What the site root shows. The same choice as "Set as homepage" on a
       row, and in the editor — this is just the quickest place to see it. --}}
  <div class="home-bar mb-4">
    <span class="home-bar-icon" aria-hidden="true">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"></path>
      </svg>
    </span>
    <div class="min-w-0">
      <div class="home-bar-label">Homepage</div>
      <div class="home-bar-hint">What visitors see at <span class="font-mono" x-text="`${window.location.host}/`"></span></div>
    </div>

    <select id="home-select" class="input ml-auto w-[260px] max-w-full" aria-label="Homepage"
            :disabled="homeSaving || !$store.auth.can('pages.update')"
            x-effect="$el.value = home.page_id === null ? '' : String(home.page_id)"
            @change="chooseHome($event.target.value)">
      <option value="">Course homepage (built in)</option>
      <template x-for="candidate in home.candidates" :key="candidate.id">
        <option :value="String(candidate.id)" x-text="candidate.title"></option>
      </template>
    </select>
    <a class="btn btn-secondary btn-sm" href="/" target="_blank" rel="noopener">View ↗</a>
    <p class="home-bar-note" x-show="!home.candidates.length">Publish a page to be able to make it the homepage.</p>
  </div>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in statusFilters" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'"
                  @click="setStatus(status)" x-text="status"></button>
        </template>
      </div>

      <div class="ml-auto flex items-center gap-2">
        <span class="toolbar-label">Show</span>
        <select class="input !h-[30px] w-[68px] !text-xs" @change="setPerPage($event.target.value)">
          <option :selected="query.per_page === 5">5</option>
          <option :selected="query.per_page === 10">10</option>
          <option :selected="query.per_page === 25">25</option>
        </select>
        <span class="toolbar-label">rows</span>
      </div>

      <div class="relative flex-[0_1_240px] min-w-[160px]">
        <input class="input !h-[30px] !text-xs" placeholder="Filter pages…"
               x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[860px]">
        <thead>
          <tr>
            <th class="cell-index">#</th>
            <template x-for="column in columns" :key="column.key">
              <th :class="column.sortable && 'sortable'"
                  @click="column.sortable && sortBy(column.sortKey || column.key)">
                <span x-text="column.label"></span>
                <span class="ml-1 text-[9px] text-accent-300"
                      x-text="column.sortable ? sortIcon(column.sortKey || column.key) : ''"></span>
              </th>
            </template>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(row, i) in rows" :key="row.id">
            <tr>
              <td class="cell-index" x-text="meta.from + i"></td>
              <td>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="cell-strong" x-text="row.title"></span>
                  <span class="tag tag-accent !h-[19px] gap-1 !px-1.5 !text-[10px]" x-show="row.is_home" title="Served at /"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"></path></svg> Homepage</span>
                </div>
              </td>
              <td class="font-mono text-[12px] text-neutral-600">
                <span x-text="row.path"></span>
                <span class="block text-[10px] text-neutral-500" x-show="row.is_home" x-text="`${row.slug} redirects here`"></span>
              </td>
              <td><span class="tag" :class="tagFor(row.status)" x-text="row.status"></span></td>
              <td>
                <span class="text-[12px]" :class="row.layout === 'default' ? 'text-neutral-500' : 'font-semibold text-ink'"
                      x-text="layoutLabel(row.layout)"></span>
              </td>
              <td class="tabular-nums" x-text="row.sections_count ?? 0"></td>
              <td class="cell-muted cell-nowrap" x-text="row.updated"></td>
              <td>
                <div class="row-actions">
                  <a :href="viewUrl(row)" target="_blank" rel="noopener" class="row-action"
                     :title="row.is_live ? 'View on the site' : 'Preview (not live yet)'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </a>
                  <button x-show="row.can.update && row.is_live && !row.is_home" @click="makeHome(row)"
                          title="Set as homepage" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"></path>
                    </svg>
                  </button>
                  <button x-show="$store.auth.can('pages.create')" @click="duplicate(row)" title="Duplicate" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="9" y="9" width="12" height="12"></rect><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"></path>
                    </svg>
                  </button>
                  <button x-show="row.can.update" @click="openBuilder(row)" title="Open the section builder" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="3" width="18" height="7"></rect><rect x="3" y="14" width="18" height="7"></rect>
                    </svg>
                  </button>
                  <button x-show="row.can.update" @click="openEdit(row)" title="Edit" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                  </button>
                  <button x-show="row.can.delete && !row.is_home" @click="askDelete(row)" title="Delete" class="row-action row-action-danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path>
                    </svg>
                  </button>
                  <span x-show="!row.can.update && !row.can.delete" class="text-xs text-neutral-500">Read only</span>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading pages…</div>
    <div x-show="isEmpty" class="table-empty">No records match this view.</div>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'"
                @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>

  {{-- template chooser --}}
  <template x-if="choosing">
    <div class="modal-backdrop" @click.self="cancelCreate()" @keydown.escape.window="cancelCreate()">
      <div class="modal w-[min(720px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Start from a template</div>

        <div class="modal-body">
          <p class="text-[13px] text-neutral-600">
            Pick a starting point. Every template is just sections you can edit or delete afterwards.
          </p>

          <div class="template-grid">
            <template x-for="template in options.templates" :key="template.value">
              <button type="button" class="template-card" @click="createFrom(template.value)">
                <span class="template-name" x-text="template.label"></span>
                <span class="template-summary" x-text="template.summary"></span>
                <span class="template-count" x-text="`${template.sections} section${template.sections === 1 ? '' : 's'}`"></span>
              </button>
            </template>
          </div>
        </div>

        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelCreate()">Cancel</button>
        </div>
      </div>
    </div>
  </template>

  {{-- delete confirmation --}}
  <template x-if="confirming">
    <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
      <div class="modal w-[min(440px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Move to trash</div>
        <div class="modal-body">
          <p class="text-sm leading-relaxed text-neutral-700">
            Move <strong class="text-ink" x-text="confirming.title"></strong> to the trash? It leaves the site now,
            and can be restored with all its sections from <strong class="text-ink">Trash</strong> for 30 days.
          </p>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
          <button class="btn btn-primary" @click="confirmDelete()">Move to trash</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
