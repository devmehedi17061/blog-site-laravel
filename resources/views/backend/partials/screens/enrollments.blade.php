<template x-if="$store.ui.screen === 'enrollments'">
<div x-data="enrollments" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Enrollments</h2>
      <div class="page-subtitle">Who is on which course — approve paid sign-ups, cancel or add students</div>
    </div>

    <button x-show="$store.auth.can('enrollments.create')" @click="openEnroll()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
      Enroll a student
    </button>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  {{-- the to-do: paid sign-ups waiting on a payment check --}}
  <div class="alert mb-4 flex flex-wrap items-center gap-3" x-show="summary.Pending > 0 && query.status !== 'Pending'">
    <span><strong x-text="summary.Pending"></strong> paid <span x-text="summary.Pending === 1 ? 'sign-up is' : 'sign-ups are'"></span> waiting for you to check the payment.</span>
    <button class="btn btn-secondary btn-sm ml-auto" @click="setStatus('Pending')">Show them</button>
  </div>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in statusFilters" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'" @click="setStatus(status)">
            <span x-text="status"></span>
            <span class="ml-1 opacity-60" x-text="countFor(status)"></span>
          </button>
        </template>
      </div>

      <select class="input !h-[30px] ml-auto w-[220px] !text-xs" x-model="query.course_id" @change="applyFilters()">
        <option value="">All courses</option>
        <template x-for="course in options.courses" :key="course.id">
          <option :value="course.id" x-text="course.title"></option>
        </template>
      </select>

      <div class="relative flex-[0_1_240px] min-w-[160px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Student, email or transaction…" x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[940px]">
        <thead>
          <tr>
            <th>Student</th>
            <th>Course</th>
            <th>Payment</th>
            <th>Progress</th>
            <th>Status</th>
            <th>Date</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr :class="row.status === 'Pending' && 'bg-warning-100/40'">
              <td>
                <div class="cell-strong" x-text="row.student"></div>
                <div class="text-[11px] text-neutral-500" x-text="row.email"></div>
              </td>
              <td>
                <a :href="row.course_url" target="_blank" rel="noopener" class="text-ink hover:text-accent" x-text="row.course"></a>
              </td>
              <td>
                <div class="font-semibold" x-text="row.amount_label"></div>
                <div class="text-[11px] text-neutral-500">
                  <span x-text="row.payment_method || '—'"></span>
                  <template x-if="row.transaction_id">
                    <span> · <code class="bg-neutral-100 px-1" x-text="row.transaction_id"></code></span>
                  </template>
                </div>
              </td>
              <td class="w-[140px]">
                <template x-if="row.status === 'Active'">
                  <div class="flex items-center gap-2">
                    <div class="h-1.5 flex-1 bg-neutral-200"><div class="h-full" :class="row.completed ? 'bg-success-800' : 'bg-accent'" :style="`width:${row.progress}%`"></div></div>
                    <span class="w-9 text-right text-[11px] font-semibold" x-text="`${row.progress}%`"></span>
                  </div>
                </template>
                <span x-show="row.status !== 'Active'" class="text-[12px] text-neutral-500">—</span>
              </td>
              <td><span class="tag" :class="tagFor(row.status)" x-text="row.completed ? 'Completed' : row.status"></span></td>
              <td class="cell-muted cell-nowrap" x-text="row.date"></td>
              <td>
                <div class="row-actions">
                  <button x-show="row.can.update && row.status !== 'Active'" class="btn btn-primary btn-sm" :disabled="busy === row.id"
                          @click="setRowStatus(row, 'Active')" x-text="row.status === 'Pending' ? 'Approve' : 'Reactivate'"></button>
                  <button x-show="row.can.update && row.status !== 'Cancelled'" class="row-action" title="Cancel enrollment" :disabled="busy === row.id" @click="setRowStatus(row, 'Cancelled')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"></circle><path d="m5.6 5.6 12.8 12.8"></path></svg>
                  </button>
                  <button x-show="row.can.update" class="row-action" title="Edit payment details and note" @click="openEdit(row)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                  </button>
                  <button x-show="row.can.delete" class="row-action row-action-danger" title="Remove" @click="remove(row)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
                  </button>
                  <span x-show="!row.can.update && !row.can.delete" class="text-xs text-neutral-500">Read only</span>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading enrollments…</div>
    <div x-show="!loading && !rows.length" class="table-empty">No enrollments match this view.</div>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'" @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>

  {{-- enroll / edit --}}
  <template x-if="modal">
    <div class="modal-backdrop" @click.self="closeModal()" @keydown.escape.window="closeModal()">
      <div class="modal w-[min(520px,100%)]">
        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="{ new: 'Enroll a student', edit: 'Edit enrollment', done: 'Student enrolled' }[modal.mode]"></span>
        </div>

        {{-- after making a new account: its password, once --}}
        <template x-if="modal.mode === 'done'">
          <div>
            <div class="modal-body flex flex-col gap-3">
              <p class="text-sm leading-relaxed text-neutral-700">A student account was made for them. Share this password — it is not shown again. They can change it under <strong>My account</strong>.</p>
              <code class="block border border-line-strong bg-neutral-100 px-3 py-2 text-[16px] font-bold tracking-wider select-all" x-text="modal.password"></code>
            </div>
            <div class="modal-foot"><button class="btn btn-primary" @click="closeModal()">Done</button></div>
          </div>
        </template>

        <template x-if="modal.mode !== 'done'">
          <form @submit.prevent="saveModal()">
            <div class="modal-body flex flex-col gap-4">
              <template x-if="modal.message"><div class="alert" x-text="modal.message"></div></template>

              <template x-if="modal.mode === 'edit'">
                <p class="text-sm text-neutral-700"><strong class="text-ink" x-text="modal.row.student"></strong> on <strong class="text-ink" x-text="modal.row.course"></strong></p>
              </template>

              <template x-if="modal.mode === 'new'">
                <div class="flex flex-col gap-4">
                  <div class="field">
                    <label>Course</label>
                    <select class="input" x-model="modal.draft.course_id" @change="pickCourse()" required :class="modalError('course_id') && 'input-invalid'">
                      <option value="">Choose a course…</option>
                      <template x-for="course in options.courses" :key="course.id">
                        <option :value="course.id" x-text="course.title"></option>
                      </template>
                    </select>
                    <p class="field-error" x-show="modalError('course_id')" x-text="modalError('course_id')"></p>
                  </div>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div class="field">
                      <label>Student's email</label>
                      <input type="email" class="input" x-model="modal.draft.email" required :class="modalError('email') && 'input-invalid'">
                      <p class="field-error" x-show="modalError('email')" x-text="modalError('email')"></p>
                    </div>
                    <div class="field">
                      <label>Name <span class="font-normal text-neutral-500">— new accounts</span></label>
                      <input class="input" x-model="modal.draft.name" :class="modalError('name') && 'input-invalid'">
                      <p class="field-error" x-show="modalError('name')" x-text="modalError('name')"></p>
                    </div>
                  </div>
                  <p class="-mt-2 text-[11px] text-neutral-500">An existing account is enrolled as it is. A new email gets a student account with a password shown once.</p>
                </div>
              </template>

              <template x-if="modal.mode === 'edit'">
                <div class="field">
                  <label>Status</label>
                  <div class="chip-group">
                    <template x-for="status in ['Active', 'Pending', 'Cancelled']" :key="status">
                      <button type="button" class="chip" :class="modal.draft.status === status && 'chip-active'" @click="modal.draft.status = status" x-text="status"></button>
                    </template>
                  </div>
                </div>
              </template>

              <div class="grid gap-3 sm:grid-cols-3">
                <div class="field">
                  <label x-text="`Amount ${options.currency}`"></label>
                  <input type="number" min="0" class="input" x-model="modal.draft.amount" placeholder="0">
                  <p class="field-error" x-show="modalError('amount')" x-text="modalError('amount')"></p>
                </div>
                <div class="field">
                  <label>Paid with</label>
                  <input class="input" x-model="modal.draft.payment_method" list="enroll-methods" placeholder="bKash, cash…">
                  <datalist id="enroll-methods">
                    <template x-for="method in options.payment_methods" :key="method"><option :value="method"></option></template>
                  </datalist>
                </div>
                <div class="field">
                  <label>Transaction ID</label>
                  <input class="input" x-model="modal.draft.transaction_id">
                </div>
              </div>

              <div class="field">
                <label>Note <span class="font-normal text-neutral-500">— only staff see it</span></label>
                <textarea class="input !min-h-[64px] py-2" x-model="modal.draft.note" maxlength="1000"></textarea>
              </div>
            </div>
            <div class="modal-foot">
              <button type="button" class="btn btn-secondary" @click="closeModal()">Cancel</button>
              <button class="btn btn-primary" :disabled="modal.saving" x-text="modal.saving ? 'Saving…' : (modal.mode === 'new' ? 'Enroll' : 'Save')"></button>
            </div>
          </form>
        </template>
      </div>
    </div>
  </template>
</div>
</template>
