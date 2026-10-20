<template x-if="$store.ui.screen === 'inbox'">
<div x-data="inbox" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Builders</div>
      <h2>Form inbox</h2>
      <div class="page-subtitle">
        Everything every form has been sent, newest first
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span class="inbox-unread-pill" x-show="summary.unread > 0"
            x-text="`${summary.unread} unread`"></span>
      <button type="button" class="btn btn-secondary" @click="markAllRead()"
              :disabled="summary.unread === 0">Mark all read</button>
      <a class="btn btn-secondary" :href="exportUrl" x-show="exportUrl">Export CSV</a>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="inbox">

    {{-- which form --}}
    <div class="inbox-forms">
      <div class="builder-col-head">Forms</div>

      <button type="button" class="inbox-form-row" :class="query.form === 'all' && 'is-active'"
              @click="setForm('all')">
        <span class="truncate font-semibold">All forms</span>
        <span class="inbox-count" x-show="summary.unread > 0" x-text="summary.unread"></span>
      </button>

      <template x-for="form in summary.forms" :key="form.id">
        <button type="button" class="inbox-form-row" :class="String(query.form) === String(form.id) && 'is-active'"
                @click="setForm(form.id)">
          <span class="truncate" x-text="form.name"></span>
          <span class="inbox-count" x-show="form.unread > 0" x-text="form.unread"></span>
        </button>
      </template>

      <div x-show="(summary.forms ?? []).length === 0" class="p-4 text-[13px] text-neutral-600">
        No forms yet.
      </div>
    </div>

    {{-- the list --}}
    <div class="inbox-list">
      <div class="toolbar !py-2">
        <label class="check !text-[12px]" title="Select everything on this page">
          <input type="checkbox" :checked="allSelected" @change="toggleAll()">
          <span class="sr-only">Select all</span>
        </label>

        <div class="chip-group">
          <template x-for="state in states" :key="state">
            <button class="chip" :class="query.state === state && 'chip-active'"
                    @click="setState(state)" x-text="state"></button>
          </template>
        </div>

        <div class="relative ml-auto flex-[0_1_220px] min-w-[140px]">
          <input class="input !h-[30px] !text-xs" placeholder="Search answers…"
                 x-model="query.search" @input="searchDebounced()">
        </div>
      </div>

      {{-- what to do with the ticked rows --}}
      <div class="inbox-bulk" x-show="selected.length > 0" x-cloak>
        <span x-text="`${selected.length} selected`"></span>
        <button type="button" class="btn btn-ghost btn-sm" @click="bulk('read')">Mark read</button>
        <button type="button" class="btn btn-ghost btn-sm" @click="bulk('unread')">Mark unread</button>
        <button type="button" class="btn btn-ghost btn-sm text-accent-700"
                x-show="$store.auth.can('forms.delete')" @click="bulk('delete')">Delete</button>
        <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="selected = []">Clear</button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <template x-for="row in rows" :key="row.id">
          <div class="inbox-row" :class="[row.unread && 'is-unread', isOpen(row) && 'is-open']">
            <label class="check inbox-row-check" @click.stop>
              <input type="checkbox" :checked="isSelected(row.id)" @change="toggleSelected(row.id)">
              <span class="sr-only">Select this submission</span>
            </label>

            <button type="button" class="inbox-row-main" @click="openRow(row)">
              <span class="inbox-row-top">
                <span class="inbox-row-form" x-text="row.form?.name ?? 'Deleted form'"></span>
                <span class="inbox-row-date" x-text="row.received_short"></span>
              </span>
              <span class="inbox-row-summary" x-text="row.summary"></span>
              <span class="inbox-row-preview" x-text="row.preview" x-show="row.preview"></span>
            </button>

            <div class="inbox-row-actions">
              <button type="button" :title="row.unread ? 'Mark read' : 'Mark unread'"
                      @click.stop="toggleRead(row)" x-text="row.unread ? '●' : '○'"></button>
              <button type="button" title="Delete" x-show="$store.auth.can('forms.delete')"
                      @click.stop="deleteRow(row)">✕</button>
            </div>
          </div>
        </template>

        <div x-show="loading" class="table-empty">Loading the inbox…</div>
        <div x-show="isEmpty" class="table-empty">
          <span x-show="query.search || query.state !== 'All' || query.form !== 'all'">
            Nothing matches this view.
          </span>
          <span x-show="!query.search && query.state === 'All' && query.form === 'all'">
            Nothing yet. Submissions from every form land here.
          </span>
        </div>
      </div>

      <div class="pagination-bar">
        <span class="pagination-count" x-text="resultCount"></span>
        <button class="page-btn" @click="goToPage(meta.current_page - 1)"
                :disabled="meta.current_page <= 1">Prev</button>
        <button class="page-btn" @click="goToPage(meta.current_page + 1)"
                :disabled="meta.current_page >= meta.last_page">Next</button>
      </div>
    </div>

    {{-- the one being read --}}
    <div class="inbox-reader">
      <template x-if="!open">
        <div class="inbox-reader-empty">
          <span x-show="!openLoading">Pick a submission to read it.</span>
          <span x-show="openLoading">Opening…</span>
        </div>
      </template>

      <template x-if="open">
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="inbox-reader-head">
            <div class="min-w-0">
              <div class="inbox-reader-title" x-text="open.summary"></div>
              <div class="inbox-reader-meta">
                <button type="button" class="link" @click="openForm(open)"
                        x-text="open.form?.name ?? 'Deleted form'"></button>
                <span x-text="`· ${open.received}`"></span>
                <span x-show="open.ip_address" x-text="`· ${open.ip_address}`"></span>
              </div>
            </div>

            <button type="button" class="btn btn-ghost btn-sm ml-auto !px-2"
                    @click="closeRow()" title="Close">✕</button>
          </div>

          <div class="inbox-reader-body">
            <dl class="answer-list">
              <template x-for="answer in open.answers" :key="answer.name">
                <div class="answer-row">
                  <dt x-text="answer.label"></dt>
                  <dd>
                    <template x-if="answer.url">
                      <a class="link" :href="answer.url" target="_blank" rel="noopener" x-text="answer.value"></a>
                    </template>
                    <template x-if="!answer.url">
                      <span x-text="answer.value || '—'"></span>
                    </template>
                  </dd>
                </div>
              </template>
            </dl>

            <p class="mt-4 text-[11px] text-neutral-500" x-show="open.answers.length === 0">
              This submission has no answers stored.
            </p>
          </div>

          <div class="inbox-reader-foot">
            <button type="button" class="btn btn-secondary btn-sm" @click="toggleRead(open)"
                    x-text="open.unread ? 'Mark read' : 'Mark unread'"></button>
            <button type="button" class="btn btn-secondary btn-sm" @click="openForm(open)">
              Open the form ↗
            </button>
            <button type="button" class="btn btn-primary btn-sm ml-auto"
                    x-show="$store.auth.can('forms.delete')" @click="deleteRow(open)">Delete</button>
          </div>
        </div>
      </template>
    </div>
  </div>
</div>
</template>
