<template x-if="$store.ui.screen === 'payments'">
<div x-data="payments" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Payments</h2>
      <div class="page-subtitle">Check what students paid — match the transaction ID with your bKash / Nagad / bank statement, then verify or reject</div>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="stat-grid mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
    <button type="button" class="stat-tile cursor-pointer text-left" style="background:var(--color-warning-100)" @click="setStatus('Pending')">
      <div class="stat-label">Waiting for you</div><div class="stat-value" x-text="summary.Pending"></div>
    </button>
    <button type="button" class="stat-tile cursor-pointer bg-surface text-left" @click="setStatus('Verified')">
      <div class="stat-label">Verified</div><div class="stat-value" x-text="summary.Verified"></div>
    </button>
    <button type="button" class="stat-tile cursor-pointer bg-surface text-left" @click="setStatus('Rejected')">
      <div class="stat-label">Rejected</div><div class="stat-value" x-text="summary.Rejected"></div>
    </button>
    <div class="stat-tile bg-neutral-900 text-white">
      <div class="stat-label !text-accent-300">Received (verified)</div><div class="stat-value" x-text="verifiedTotal"></div>
    </div>
  </div>

  <div class="panel">
    <div class="toolbar">
      <div class="chip-group">
        <template x-for="status in ['Pending', 'Verified', 'Rejected', 'All']" :key="status">
          <button class="chip" :class="query.status === status && 'chip-active'" @click="setStatus(status)">
            <span x-text="status === 'Pending' ? 'Waiting' : status"></span>
            <span class="ml-1 opacity-60" x-text="countFor(status)"></span>
          </button>
        </template>
      </div>
      <select class="input !h-[30px] ml-auto w-[160px] !text-xs" x-model="query.method" @change="applyFilters()">
        <option value="">All methods</option>
        <template x-for="m in methods" :key="m"><option :value="m" x-text="m"></option></template>
      </select>
      <div class="relative flex-[0_1_260px] min-w-[160px]">
        <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
        <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Transaction ID, number, student…" x-model="query.search" @input="searchDebounced()">
      </div>
    </div>

    <div x-show="loading" class="table-empty">Loading payments…</div>
    <div x-show="!loading && !rows.length" class="table-empty" x-text="query.status === 'Pending' ? 'Nothing waiting — every payment has been checked. ✓' : 'No payments match this view.'"></div>

    {{-- one card per payment: everything needed to decide, in one place --}}
    <template x-for="row in rows" :key="row.id">
      <div class="flex flex-wrap gap-5 border-b border-line p-5 last:border-0" :class="row.status === 'Pending' && 'bg-[#fffdf6]'">

        {{-- screenshot --}}
        <div class="w-[120px] flex-none">
          <template x-if="row.proof_url && !row.proof_is_pdf">
            <button type="button" class="block h-[120px] w-[120px] cursor-zoom-in overflow-hidden border border-line-strong bg-neutral-100" @click="preview = row" title="View the screenshot">
              <img :src="row.proof_url" alt="Payment screenshot" class="h-full w-full object-cover" loading="lazy">
            </button>
          </template>
          <template x-if="row.proof_url && row.proof_is_pdf">
            <a :href="row.proof_url" target="_blank" rel="noopener" class="grid h-[120px] w-[120px] place-items-center border border-line-strong bg-neutral-100 text-[12px] font-bold text-neutral-700">📄 PDF proof</a>
          </template>
          <template x-if="!row.proof_url">
            <div class="grid h-[120px] w-[120px] place-items-center border border-dashed border-line-strong p-2 text-center text-[11px] text-neutral-500">No screenshot</div>
          </template>
        </div>

        {{-- details --}}
        <div class="min-w-[260px] flex-1">
          <div class="mb-1 flex flex-wrap items-center gap-2">
            <span class="tag" :class="{ Pending: 'tag-accent-2', Verified: 'tag-success', Rejected: 'tag-neutral' }[row.status]" x-text="row.status"></span>
            <span class="text-[11px] text-neutral-500" x-text="`${row.invoice} · sent ${row.submitted_ago}`"></span>
          </div>
          <div class="cell-strong text-[15px]">
            <span x-text="row.student"></span>
            <span class="font-normal text-neutral-500"> → </span>
            <a :href="row.course_url" target="_blank" rel="noopener" class="font-semibold hover:text-accent" x-text="row.course"></a>
          </div>
          <div class="text-[12px] text-neutral-500" x-text="[row.email, row.phone].filter(Boolean).join(' · ')"></div>

          <div class="mt-3 grid grid-cols-2 gap-x-5 gap-y-2 text-[13px] sm:grid-cols-4">
            <div><div class="text-[10px] font-bold tracking-[0.1em] text-neutral-500 uppercase">Method</div><span x-text="row.method"></span></div>
            <div><div class="text-[10px] font-bold tracking-[0.1em] text-neutral-500 uppercase">Paid from</div><span class="font-mono" x-text="row.sender_number || '—'"></span></div>
            <div>
              <div class="text-[10px] font-bold tracking-[0.1em] text-neutral-500 uppercase">Transaction ID</div>
              <button type="button" class="font-mono font-bold hover:text-accent" title="Copy" @click="copy(row.transaction_id)" x-text="row.transaction_id"></button>
            </div>
            <div>
              <div class="text-[10px] font-bold tracking-[0.1em] text-neutral-500 uppercase">Amount</div>
              <span class="font-bold" :class="row.short && 'text-accent-700'" x-text="row.amount_label"></span>
              <span class="text-[11px] text-neutral-500" x-show="row.short" x-text="`of ${row.expected_label}`"></span>
            </div>
          </div>

          <template x-if="row.note">
            <p class="mt-2 text-[13px] text-neutral-700"><span class="font-semibold">Student's note:</span> <span x-text="row.note"></span></p>
          </template>

          {{-- warnings worth a second look --}}
          <template x-if="row.status === 'Pending' && (row.duplicates.length || row.short)">
            <div class="mt-3 flex flex-col gap-1 border-l-[3px] border-accent bg-accent-100 px-3 py-2 text-[12px] text-accent-800">
              <template x-for="flag in flags(row).filter(f => !f.startsWith('No screenshot'))"><span x-text="'⚠ ' + flag"></span></template>
            </div>
          </template>

          <template x-if="row.status === 'Rejected'">
            <p class="mt-2 text-[13px] text-neutral-700"><span class="font-semibold">Rejected:</span> <span x-text="row.rejection_reason"></span>
              <span class="text-neutral-500" x-text="`— ${row.reviewer ?? ''}, ${row.reviewed_at}`"></span></p>
          </template>
          <template x-if="row.status === 'Verified'">
            <p class="mt-2 text-[12px] text-neutral-500" x-text="`Verified by ${row.reviewer ?? '—'} on ${row.reviewed_at}`"></p>
          </template>
        </div>

        {{-- actions --}}
        <div class="flex w-full flex-row gap-2 sm:w-[150px] sm:flex-col">
          <template x-if="row.can.review && row.status !== 'Verified'">
            <button class="btn btn-primary flex-1 sm:flex-none" :disabled="busy === row.id" @click="verify(row)">✓ Verify</button>
          </template>
          <template x-if="row.can.review && row.status === 'Pending'">
            <button class="btn btn-secondary flex-1 sm:flex-none" :disabled="busy === row.id" @click="openReject(row)">✕ Reject</button>
          </template>
          <template x-if="row.receipt_url">
            <a :href="row.receipt_url" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Receipt ↗</a>
          </template>
        </div>
      </div>
    </template>

    <div class="pagination-bar">
      <span class="pagination-count" x-text="resultCount"></span>
      <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
      <template x-for="page in pageNumbers" :key="page">
        <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'" @click="goToPage(page)" x-text="page"></button>
      </template>
      <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
    </div>
  </div>

  {{-- reject: with a reason the student will read --}}
  <template x-if="rejecting">
    <div class="modal-backdrop" @click.self="rejecting = null" @keydown.escape.window="rejecting = null">
      <div class="modal w-[min(520px,100%)]">
        <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Reject payment</div>
        <form @submit.prevent="confirmReject()">
          <div class="modal-body flex flex-col gap-3">
            <p class="text-sm text-neutral-700">
              <strong class="text-ink" x-text="rejecting.row.student"></strong> will see this reason and can send the payment again.
              Their access to <strong class="text-ink" x-text="rejecting.row.course"></strong> closes until a payment is verified.
            </p>
            <div class="flex flex-col gap-1.5">
              <template x-for="r in reasons" :key="r">
                <label class="option-card !py-2">
                  <input type="radio" :value="r" x-model="rejecting.reason">
                  <span class="text-[13px]" x-text="r"></span>
                </label>
              </template>
            </div>
            <div class="field">
              <label>Reason shown to the student</label>
              <textarea class="input !min-h-[70px] py-2" x-model="rejecting.reason" maxlength="500" required></textarea>
              <p class="field-error" x-show="rejecting.error" x-text="rejecting.error"></p>
            </div>
          </div>
          <div class="modal-foot">
            <button type="button" class="btn btn-secondary" @click="rejecting = null">Cancel</button>
            <button class="btn btn-danger text-white" :disabled="rejecting.saving" x-text="rejecting.saving ? 'Rejecting…' : 'Reject payment'"></button>
          </div>
        </form>
      </div>
    </div>
  </template>

  {{-- the screenshot, large --}}
  <template x-if="preview">
    <div class="modal-backdrop" @click.self="preview = null" @keydown.escape.window="preview = null">
      <div class="modal flex max-h-[94vh] w-[min(760px,100%)] flex-col">
        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="`${preview.invoice} · ${preview.method} · ${preview.transaction_id}`"></span>
          <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="preview = null">Close</button>
        </div>
        <div class="overflow-auto bg-neutral-900 p-3"><img :src="preview.proof_url" alt="Payment screenshot" class="mx-auto max-h-[78vh]"></div>
        <div class="modal-foot" x-show="preview.status === 'Pending' && preview.can.review">
          <button class="btn btn-secondary" @click="openReject(preview); preview = null">✕ Reject</button>
          <button class="btn btn-primary" @click="verify(preview); preview = null">✓ Verify</button>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
