{{-- What came back. Read and delete only — a submission is a record of
     something that happened, so there is nothing here to edit. --}}
<div class="panel">
  <div class="toolbar">
    <span class="text-[13px] text-neutral-700">
      <span x-text="submissionsMeta.total ?? 0"></span> submission(s)
    </span>

    <div class="ml-auto flex items-center gap-2">
      <button type="button" class="btn btn-ghost btn-sm" @click="loadSubmissions(submissionsMeta.current_page)">
        Refresh
      </button>
      <a class="btn btn-secondary btn-sm" :href="exportUrl" x-show="(submissionsMeta.total ?? 0) > 0">
        Export CSV
      </a>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="data-table min-w-[620px]">
      <thead>
        <tr>
          <th class="cell-index">#</th>
          <th>Summary</th>
          <th>Received</th>
          <th>From</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <template x-for="(row, i) in submissions" :key="row.id">
          <tr>
            <td class="cell-index" x-text="(submissionsMeta.from ?? 1) + i"></td>
            <td class="cell-strong">
              <button type="button" class="link text-left" @click="openSubmission = row" x-text="row.summary"></button>
            </td>
            <td class="cell-muted cell-nowrap" x-text="row.received"></td>
            <td class="font-mono text-[12px] text-neutral-600" x-text="row.ip_address ?? '—'"></td>
            <td>
              <div class="row-actions">
                <button class="row-action" title="Read" @click="openSubmission = row">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
                <button class="row-action row-action-danger" title="Delete" x-show="$store.auth.can('forms.delete')"
                        @click="deleteSubmission(row)">
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

  <div x-show="submissionsLoading" class="table-empty">Loading submissions…</div>
  <div x-show="!submissionsLoading && submissions.length === 0" class="table-empty">
    Nothing yet. Submissions from the public form land here.
  </div>

  <div class="pagination-bar" x-show="(submissionsMeta.last_page ?? 1) > 1">
    <span class="pagination-count"
          x-text="`${submissionsMeta.from}–${submissionsMeta.to} of ${submissionsMeta.total}`"></span>
    <button class="page-btn" @click="loadSubmissions(submissionsMeta.current_page - 1)"
            :disabled="submissionsMeta.current_page <= 1">Prev</button>
    <button class="page-btn" @click="loadSubmissions(submissionsMeta.current_page + 1)"
            :disabled="submissionsMeta.current_page >= submissionsMeta.last_page">Next</button>
  </div>
</div>

{{-- one submission --}}
<template x-if="openSubmission">
  <div class="modal-backdrop" @click.self="openSubmission = null" @keydown.escape.window="openSubmission = null">
    <div class="modal w-[min(620px,100%)] !max-h-[85vh]">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span x-text="`Received ${openSubmission.received}`"></span>
        <button class="ml-auto text-white/70 hover:text-white" @click="openSubmission = null" title="Close">✕</button>
      </div>

      <div class="modal-body">
        <dl class="answer-list">
          <template x-for="answer in openSubmission.answers" :key="answer.name">
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

        <p class="text-[11px] text-neutral-500">
          Sent from <span class="font-mono" x-text="openSubmission.ip_address ?? 'an unknown address'"></span>.
        </p>
      </div>

      <div class="modal-foot">
        <button class="btn btn-secondary" @click="openSubmission = null">Close</button>
        <button class="btn btn-primary" x-show="$store.auth.can('forms.delete')"
                @click="deleteSubmission(openSubmission)">Delete</button>
      </div>
    </div>
  </div>
</template>
