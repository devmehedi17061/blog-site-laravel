<template x-if="$store.ui.screen === 'discussions'">
<div x-data="discussions" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Q&amp;A</h2>
      <div class="page-subtitle">Questions students asked under lessons. Reply here — the student is notified and the question is marked answered.</div>
    </div>
  </div>

  <template x-if="error"><div class="alert mb-4" x-text="error"></div></template>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in ['Open', 'Answered', 'All']" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'" @click="setStatus(status)">
            <span x-text="status"></span>
            <span class="ml-1 opacity-60" x-show="status !== 'All'" x-text="summary[status]"></span>
          </button>
        </template>
      </div>
      <select class="input !h-[30px] ml-auto w-[220px] !text-xs" x-model="query.course_id" @change="applyFilters()">
        <option value="">All courses</option>
        <template x-for="c in courses" :key="c.id"><option :value="c.id" x-text="c.title"></option></template>
      </select>
      <div class="relative flex-[0_1_220px] min-w-[150px]">
        <input class="input !h-[30px] !text-xs" placeholder="Search questions…" x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div x-show="loading" class="table-empty">Loading questions…</div>
    <div x-show="!loading && !rows.length" class="table-empty" x-text="query.status === 'Open' ? 'No open questions — every student has an answer. ✓' : 'No questions match.'"></div>

    <template x-for="row in rows" :key="row.id">
      <div class="border-b border-line p-5 last:border-0" :class="!row.resolved && 'bg-[#fffdf6]'">
        <div class="mb-1 flex flex-wrap items-center gap-2 text-[12px] text-neutral-500">
          <span class="tag" :class="row.resolved ? 'tag-success' : 'tag-accent-2'" x-text="row.resolved ? 'Answered' : 'Open'"></span>
          <strong class="text-[13px] text-ink" x-text="row.author"></strong>
          <span x-text="row.ago"></span>
          <span>·</span>
          <span x-text="`${row.course} › ${row.lesson ?? '—'}`"></span>
          <a :href="row.url" target="_blank" rel="noopener" class="ml-auto font-semibold text-accent-700" x-show="row.url">Open in lesson ↗</a>
        </div>
        <p class="whitespace-pre-line text-[15px] leading-relaxed text-ink" x-text="row.body"></p>

        <div class="mt-3 flex flex-col gap-2 border-l-2 border-line pl-4" x-show="row.replies.length">
          <template x-for="reply in row.replies" :key="reply.id">
            <div class="text-[14px]">
              <div class="mb-0.5 flex items-center gap-2 text-[12px] text-neutral-500">
                <strong class="text-ink" x-text="reply.author"></strong>
                <span class="qa-staff" x-show="reply.staff">Instructor</span>
                <span x-text="reply.ago"></span>
              </div>
              <p class="whitespace-pre-line text-neutral-800" x-text="reply.body"></p>
            </div>
          </template>
        </div>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
          <textarea class="input !min-h-[44px] flex-1 py-2 !text-[14px]" rows="2" maxlength="3000" placeholder="Write your answer…" x-model="replies[row.id]"
                    @keydown.ctrl.enter="reply(row)"></textarea>
          <div class="flex gap-2">
            <button class="btn btn-primary" :disabled="busy === row.id || !(replies[row.id] || '').trim()" @click="reply(row)">Reply</button>
            <button class="btn btn-secondary btn-sm !h-[36px]" @click="toggle(row)" x-text="row.resolved ? 'Reopen' : 'Mark answered'"></button>
            <button class="row-action row-action-danger !h-[36px]" title="Delete" @click="remove(row)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="meta.total ? `${meta.total} question${meta.total === 1 ? '' : 's'}` : ''"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <span class="px-2 text-[12px]" x-text="`${meta.current_page} / ${meta.last_page}`"></span>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>
</div>
</template>
