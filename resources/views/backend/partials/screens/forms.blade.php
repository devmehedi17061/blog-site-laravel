<template x-if="$store.ui.screen === 'forms' && !$store.ui.editing">
<div x-data="forms" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Builders</div>
      <h2>Form builder</h2>
      <div class="page-subtitle">Build a form once, drop it on any page, read what comes back</div>
    </div>

    <button x-show="$store.auth.can('forms.create')" @click="openCreate()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M5 12h14"></path><path d="M12 5v14"></path>
      </svg>
      New form
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in statusFilters" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'"
                  @click="setStatus(status)" x-text="status"></button>
        </template>
      </div>

      <div class="relative ml-auto flex-[0_1_240px] min-w-[160px]">
        <input class="input !h-[30px] !text-xs" placeholder="Filter forms…"
               x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[760px]">
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
              <td class="cell-strong" x-text="row.name"></td>
              <td class="font-mono text-[12px] text-neutral-600" x-text="`/forms/${row.slug}`"></td>
              <td><span class="tag" :class="tagFor(row.status)" x-text="row.status"></span></td>
              <td class="tabular-nums" x-text="row.fields_count ?? 0"></td>
              <td class="tabular-nums">
                <span x-text="row.submissions_count ?? 0"></span>
                <span class="ml-1 text-[10px] text-accent-700" x-show="(row.submissions_count ?? 0) > 0">new</span>
              </td>
              <td class="cell-muted cell-nowrap" x-text="row.updated"></td>
              <td>
                <div class="row-actions">
                  <button @click="openBuilder(row)" title="Open the builder" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect x="3" y="3" width="18" height="7"></rect><rect x="3" y="14" width="18" height="7"></rect>
                    </svg>
                  </button>
                  <button x-show="row.can.delete" @click="askDelete(row)" title="Delete" class="row-action row-action-danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading forms…</div>
    <div x-show="isEmpty" class="table-empty">No forms yet. Build the first one.</div>

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

  {{-- new form --}}
  <template x-if="creating">
    <div class="modal-backdrop" @click.self="cancelCreate()" @keydown.escape.window="cancelCreate()">
      <div class="modal w-[min(460px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> New form</div>

        <div class="modal-body">
          <div class="field">
            <label>Name</label>
            <input class="input" x-model="newName" placeholder="Contact, Newsletter, Job application…"
                   @keydown.enter="create()" autofocus>
            <p class="field-error" x-show="createError" x-text="createError"></p>
          </div>
          <p class="text-[12px] text-neutral-600">
            The builder opens next, where the fields are added.
          </p>
        </div>

        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelCreate()">Cancel</button>
          <button class="btn btn-primary" @click="create()">Create and build</button>
        </div>
      </div>
    </div>
  </template>

  {{-- delete confirmation --}}
  <template x-if="confirming">
    <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
      <div class="modal w-[min(460px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Delete form</div>
        <div class="modal-body">
          <p class="text-sm leading-relaxed text-neutral-700">
            Delete <strong class="text-ink" x-text="confirming.name"></strong>, its fields and
            <strong class="text-ink" x-text="confirming.submissions_count ?? 0"></strong> submission(s)?
            This cannot be undone, and any section showing it will render nothing.
          </p>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
          <button class="btn btn-primary" @click="confirmDelete()">Delete form</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
