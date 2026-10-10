<template x-if="$store.ui.screen === 'coupons'">
<div x-data="coupons" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Coupons</h2>
      <div class="page-subtitle">Discount codes for courses — percent or fixed, with limits and dates. Share them as a code or a promo link.</div>
    </div>
    <button x-show="$store.auth.can('coupons.create')" @click="openCreate()" class="btn btn-primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
      New coupon
    </button>
  </div>

  <template x-if="error"><div class="alert mb-4" x-text="error"></div></template>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in statusFilters" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'" @click="setStatus(status)" x-text="status"></button>
        </template>
      </div>
      <div class="relative ml-auto flex-[0_1_240px] min-w-[160px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Find a code…" x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="data-table min-w-[900px]">
        <thead>
          <tr><th>Code</th><th>Discount</th><th>For</th><th>Used</th><th>Given away</th><th>Ends</th><th>Status</th><th class="text-right">Actions</th></tr>
        </thead>
        <tbody>
          <template x-for="row in rows" :key="row.id">
            <tr>
              <td>
                <span class="coupon-chip" x-text="row.code"></span>
                <div class="mt-1 text-[11px] text-neutral-500" x-text="row.description"></div>
              </td>
              <td class="cell-strong" x-text="row.label"></td>
              <td class="max-w-[220px] truncate" :title="row.applies_to" x-text="row.applies_to"></td>
              <td>
                <span x-text="row.used"></span><span class="text-neutral-500" x-text="row.max_uses ? ` / ${row.max_uses}` : ' / ∞'"></span>
                <div class="mt-1 h-1 w-16 bg-neutral-200" x-show="row.max_uses"><div class="h-full bg-accent" :style="`width:${Math.min(100, row.used / row.max_uses * 100)}%`"></div></div>
              </td>
              <td class="cell-nowrap" x-text="row.discount_given"></td>
              <td class="cell-muted cell-nowrap" x-text="row.expires_label ?? 'Never'"></td>
              <td><span class="tag" :class="tagFor(row.state)" x-text="row.state"></span></td>
              <td>
                <div class="row-actions">
                  <button class="row-action" title="Copy promo link" @click="copyLink(row)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
                  </button>
                  <button x-show="row.can.update" @click="openEdit(row)" title="Edit" class="row-action">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                  </button>
                  <button x-show="row.can.delete" @click="askDelete(row)" title="Delete" class="row-action row-action-danger">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div x-show="loading" class="table-empty">Loading coupons…</div>
    <div x-show="isEmpty" class="table-empty">No coupons yet. Make one for a launch, a holiday, or a returning student.</div>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'" @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>

  {{-- create / edit --}}
  <template x-if="modal">
    <div class="modal-backdrop" @keydown.escape.window="closeModal()">
      <div class="modal flex max-h-[94vh] w-[min(680px,100%)] flex-col">
        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="modal.mode === 'new' ? 'New coupon' : 'Edit coupon'"></span>
          <span class="ml-auto text-[12px] font-normal normal-case tracking-normal text-neutral-400" x-text="preview"></span>
        </div>
        <form @submit.prevent="save()" class="flex min-h-0 flex-col">
          <div class="modal-body flex flex-col gap-4 overflow-y-auto">
            <template x-if="modal.message"><div class="alert" x-text="modal.message"></div></template>

            <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
              <div class="field">
                <label>Code</label>
                <input class="input font-mono uppercase tracking-wider" x-model="modal.draft.code" maxlength="40" placeholder="EID50" required
                       :class="fieldError('code') && 'input-invalid'">
                <p class="field-error" x-show="fieldError('code')" x-text="fieldError('code')"></p>
              </div>
              <div class="field">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-secondary" @click="generateCode()">Generate</button>
              </div>
            </div>

            <div class="field">
              <label>Note <span class="font-normal text-neutral-500">— only staff see it</span></label>
              <input class="input" x-model="modal.draft.description" maxlength="255" placeholder="Eid campaign on Facebook">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="field">
                <label>Discount</label>
                <div class="flex gap-2">
                  <select class="input w-[150px]" x-model="modal.draft.type">
                    <option value="percent">Percent %</option>
                    <option value="fixed" x-text="`Fixed ${options.currency ?? ''}`"></option>
                  </select>
                  <input type="number" min="0" step="any" class="input" x-model="modal.draft.value" required :class="fieldError('value') && 'input-invalid'">
                </div>
                <p class="field-error" x-show="fieldError('value')" x-text="fieldError('value')"></p>
              </div>
              <div class="field">
                <label>Only for courses of at least <span class="font-normal text-neutral-500">(optional)</span></label>
                <input type="number" min="0" step="any" class="input" x-model="modal.draft.min_amount" placeholder="Any price">
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="field">
                <label>Total uses</label>
                <input type="number" min="1" class="input" x-model="modal.draft.max_uses" placeholder="Unlimited">
                <p class="field-error" x-show="fieldError('max_uses')" x-text="fieldError('max_uses')"></p>
              </div>
              <div class="field">
                <label>Uses per student</label>
                <input type="number" min="0" class="input" x-model="modal.draft.max_uses_per_user">
                <p class="mt-1 text-[11px] text-neutral-500">0 = no limit per student.</p>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div class="field">
                <label>Starts <span class="font-normal text-neutral-500">(optional)</span></label>
                <input type="datetime-local" class="input" x-model="modal.draft.starts_at">
              </div>
              <div class="field">
                <label>Ends <span class="font-normal text-neutral-500">(optional)</span></label>
                <input type="datetime-local" class="input" x-model="modal.draft.expires_at" :class="fieldError('expires_at') && 'input-invalid'">
                <p class="field-error" x-show="fieldError('expires_at')" x-text="fieldError('expires_at')"></p>
              </div>
            </div>

            <div class="field">
              <label class="flex">
                Works on
                <span class="ml-auto font-normal text-neutral-500" x-text="modal.draft.course_ids.length ? `${modal.draft.course_ids.length} selected` : 'Every course'"></span>
              </label>
              <input class="input !h-[32px] mb-2 !text-[13px]" placeholder="Find a course…" x-model="courseSearch">
              <div class="max-h-[180px] overflow-y-auto border border-line-strong">
                <template x-for="course in visibleCourses" :key="course.id">
                  <label class="flex cursor-pointer items-center gap-2 border-b border-line px-3 py-2 text-[13px] last:border-0 hover:bg-muted">
                    <input type="checkbox" :checked="modal.draft.course_ids.includes(course.id)" @change="toggleCourse(course.id)" class="accent-[var(--color-accent)]">
                    <span class="flex-1" x-text="course.title"></span>
                    <span class="text-neutral-500" x-text="course.price"></span>
                  </label>
                </template>
              </div>
              <p class="mt-1 text-[11px] text-neutral-500">Leave all unticked for a coupon that works on every course.</p>
            </div>

            <label class="switch-row !p-0">
              <input type="checkbox" class="switch" x-model="modal.draft.is_active">
              <span><strong>Active</strong><span>Switch off to pause the coupon without deleting it.</span></span>
            </label>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-secondary" @click="closeModal()">Cancel</button>
            <button class="btn btn-primary" :disabled="modal.saving" x-text="modal.saving ? 'Saving…' : (modal.mode === 'new' ? 'Create coupon' : 'Save')"></button>
          </div>
        </form>
      </div>
    </div>
  </template>

  <template x-if="confirming">
    <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
      <div class="modal w-[min(440px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Delete coupon</div>
        <div class="modal-body">
          <p class="text-sm leading-relaxed text-neutral-700">
            Delete <strong class="text-ink" x-text="confirming.code"></strong>? It stops working at once. Payments that used it keep the discount they got.
            To pause it instead, switch it off.
          </p>
        </div>
        <div class="modal-foot">
          <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
          <button class="btn btn-danger text-white" @click="confirmDelete()">Delete</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
