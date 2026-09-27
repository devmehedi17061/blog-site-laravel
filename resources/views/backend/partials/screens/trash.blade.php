<template x-if="$store.ui.screen === 'trash'">
<div x-data="trash" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Trash</h2>
      <div class="page-subtitle">
        Deleted posts, pages and files wait here for <span x-text="days"></span> days, then are deleted for good.
      </div>
    </div>

    <button class="btn btn-secondary" x-show="canEmpty" @click="emptyTrash()" :disabled="busy">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path>
      </svg>
      <span x-text="`Empty ${tab.label.toLowerCase()} trash`"></span>
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group" role="tablist" aria-label="What to show">
        <template x-for="t in tabs" :key="t.key">
          <button class="chip gap-1.5" role="tab" :aria-selected="type === t.key"
                  :class="type === t.key && 'chip-active'" @click="setType(t.key)">
            <span x-text="t.label"></span>
            <span class="trash-count" x-text="counts[t.key] ?? 0"></span>
          </button>
        </template>
      </div>

      <div class="relative ml-auto min-w-[160px] flex-[0_1_240px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500"
             width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path>
        </svg>
        <input class="input !h-[30px] !pl-8 !text-xs" :placeholder="`Filter ${tab.label.toLowerCase()}…`"
               x-model="search" @input="searchDebounced()" aria-label="Filter the trash">
      </div>
    </div>

    {{-- what to do with the ticked rows --}}
    <div class="bulk-bar" x-show="selected.length" x-transition.opacity>
      <strong x-text="`${selected.length} selected`"></strong>
      <button class="btn btn-secondary btn-sm ml-auto" @click="restore(selectedRows)" :disabled="busy || !canRestoreSelected">Restore</button>
      <button class="btn btn-primary btn-sm" @click="deleteForGood(selectedRows)" :disabled="busy || !canDeleteSelected">Delete for good</button>
      <button class="btn btn-ghost btn-sm" @click="selected = []">Clear</button>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[720px]">
        <thead>
          <tr>
            <th class="w-[44px]">
              <input type="checkbox" class="h-4 w-4 accent-[var(--color-accent)]" aria-label="Select all on this page"
                     :checked="allSelected" @change="toggleAll()" :disabled="!rows.length">
            </th>
            <th x-text="type === 'media' ? 'File' : 'Title'"></th>
            <th>Deleted</th>
            <th>Deleted by</th>
            <th>Kept for</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr :class="isSelected(row.id) && 'trash-row-selected'">
              <td>
                <input type="checkbox" class="h-4 w-4 accent-[var(--color-accent)]" :aria-label="`Select ${row.title}`"
                       :checked="isSelected(row.id)" @change="toggle(row.id)">
              </td>
              <td>
                <div class="flex items-center gap-3">
                  <template x-if="row.thumb">
                    <img :src="row.thumb" alt="" class="trash-thumb" loading="lazy">
                  </template>
                  <div class="min-w-0">
                    <div class="cell-strong truncate" x-text="row.title"></div>
                    <div class="text-[12px] text-neutral-500" x-text="row.details"></div>
                  </div>
                </div>
              </td>
              <td class="cell-muted cell-nowrap" x-text="row.deleted_on"></td>
              <td class="cell-muted" x-text="row.deleted_by ?? '—'"></td>
              <td class="cell-nowrap">
                <span class="tag" :class="row.days_left <= 3 ? 'tag-accent' : 'tag-neutral'" x-text="daysLabel(row)"></span>
              </td>
              <td>
                <div class="row-actions">
                  <button class="btn btn-secondary btn-sm" x-show="row.can.restore" @click="restore([row])" :disabled="busy">Restore</button>
                  <button class="row-action row-action-danger" x-show="row.can.delete" @click="deleteForGood([row])" :disabled="busy"
                          title="Delete for good" aria-label="Delete for good">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path>
                    </svg>
                  </button>
                  <span x-show="!row.can.restore && !row.can.delete" class="text-xs text-neutral-500">Not yours</span>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading && !rows.length" class="table-empty">Loading the trash…</div>
    <div x-show="!loading && !rows.length" class="table-empty">
      <span x-text="search ? 'Nothing in the trash matches that.' : `No ${tab.label.toLowerCase()} in the trash.`"></span>
    </div>

    <div class="pagination-bar" x-show="meta.last_page > 1">
      <span class="pagination-count" x-text="`Showing ${meta.from}–${meta.to} of ${meta.total}`"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>
</div>
</template>
