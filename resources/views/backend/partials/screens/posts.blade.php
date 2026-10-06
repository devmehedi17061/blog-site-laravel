<template x-if="$store.ui.screen === 'posts' && !$store.ui.editing">
<div x-data="posts" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Posts</h2>
      <div class="page-subtitle">Blog entries, categories and publishing state</div>
    </div>

    <button x-show="$store.auth.can('posts.create')" @click="openCreate()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
        <path d="M5 12h14"></path><path d="M12 5v14"></path>
      </svg>
      New post
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

      <div class="ml-auto flex items-center gap-2">
        <span class="toolbar-label">Show</span>
        <select class="input btn-sm !h-[30px] w-[68px] !text-xs" @change="setPerPage($event.target.value)">
          <option :selected="query.per_page === 5">5</option>
          <option :selected="query.per_page === 10">10</option>
          <option :selected="query.per_page === 25">25</option>
        </select>
        <span class="toolbar-label">rows</span>
      </div>

      <div class="relative flex-[0_1_240px] min-w-[160px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500"
             width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path>
        </svg>
        <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Filter posts…"
               x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[660px]">
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
              <td class="cell-strong" x-text="row.title"></td>
              <td x-text="row.author ?? '—'"></td>
              <td x-text="row.category ?? '—'"></td>
              <td><span class="tag" :class="tagFor(row.status)" x-text="row.status"></span></td>
              <td class="cell-muted cell-nowrap" x-text="row.date"></td>
              <td>
                <div class="row-actions">
                  <button x-show="row.can.update" @click="openEdit(row)" title="Edit" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                    </svg>
                  </button>
                  <button x-show="row.can.delete" @click="askDelete(row)" title="Delete" class="row-action row-action-danger">
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

    <div x-show="loading" class="table-empty">Loading posts…</div>
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

  @include('backend.partials.screens.posts-dialogs')
</div>
</template>
