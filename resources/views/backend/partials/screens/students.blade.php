<template x-if="$store.ui.screen === 'students'">
<div x-data="students" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Students</h2>
      <div class="page-subtitle">Learner accounts and how far they have got. Passwords and suspensions are under User management.</div>
    </div>
    <a class="btn btn-secondary" href="/admin/api/students/export">⬇ Download CSV</a>
    <button x-show="$store.auth.can('users.view')" class="btn btn-secondary" @click="$store.ui.go('users')">User management</button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="panel">
    <div class="toolbar">
      <span class="toolbar-label" x-text="resultCount"></span>
      <div class="relative ml-auto flex-[0_1_260px] min-w-[160px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Name or email…" x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[760px]">
        <thead>
          <tr>
            <th>Student</th>
            <th>Learning</th>
            <th>Completed</th>
            <th>Awaiting approval</th>
            <th>Joined</th>
            <th>Last seen</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr>
              <td>
                <div class="flex items-center gap-3">
                  <span class="grid h-8 w-8 flex-none place-items-center bg-neutral-900 font-heading text-[11px] font-bold text-white" x-text="row.initials"></span>
                  <div class="min-w-0">
                    <div class="cell-strong" x-text="row.name"></div>
                    <div class="text-[11px] text-neutral-500" x-text="row.email"></div>
                  </div>
                </div>
              </td>
              <td x-text="row.active_count"></td>
              <td><span :class="row.completed_count && 'font-semibold text-success-800'" x-text="row.completed_count"></span></td>
              <td>
                <button x-show="row.pending_count" class="tag tag-accent-2 cursor-pointer" @click="$store.ui.go('enrollments', 'pending')" x-text="`${row.pending_count} pending`"></button>
                <span x-show="!row.pending_count" class="text-neutral-500">—</span>
              </td>
              <td class="cell-muted cell-nowrap" x-text="row.joined"></td>
              <td class="cell-muted cell-nowrap" x-text="row.last_seen"></td>
              <td><span class="tag" :class="row.status === 'Active' ? 'tag-success' : 'tag-neutral'" x-text="row.status"></span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading students…</div>
    <div x-show="!loading && !rows.length" class="table-empty">No students match. They appear here once they register or are enrolled.</div>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'" @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>
</div>
</template>
