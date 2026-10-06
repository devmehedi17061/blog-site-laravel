<template x-if="$store.ui.screen === 'forms' && $store.ui.editing">
<div x-data="formBuilder">

  <div class="editor-bar">
    <button type="button" class="btn btn-ghost btn-sm" @click="backToList()">← Forms</button>

    <span class="font-heading text-[12px] font-bold tracking-[0.08em] text-neutral-600 uppercase">Form builder</span>

    <span class="tag ml-1" :class="draft.status === 'Active' ? 'tag-accent' : 'tag-neutral'"
          x-text="draft.status"></span>

    <span class="ml-2 font-mono text-[11px] text-neutral-600" x-text="publicNote"></span>

    <div class="ml-auto flex items-center gap-2">
      <span class="counter" x-show="dirty">Unsaved changes</span>
      <button type="button" class="btn btn-primary" @click="save()" :disabled="saving"
              x-text="saving ? 'Saving…' : 'Save form'"></button>
    </div>
  </div>

  <div class="p-6">
    <template x-if="error">
      <div class="alert mb-5" x-text="error"></div>
    </template>

    <div x-show="loading" class="panel table-empty">Loading the builder…</div>

    <div x-show="!loading">
      <div class="tabs mb-5">
        <button type="button" class="tab" :class="tab === 'fields' && 'tab-active'" @click="tab = 'fields'">
          Fields <span class="ml-1.5 text-[10px] tabular-nums" x-text="`(${fields.length})`"></span>
        </button>
        <button type="button" class="tab" :class="tab === 'settings' && 'tab-active'" @click="tab = 'settings'">Settings</button>
        <button type="button" class="tab" :class="tab === 'submissions' && 'tab-active'" @click="openSubmissions()">
          Submissions
          <span class="ml-1.5 text-[10px] tabular-nums" x-text="`(${draft.submissions_count ?? 0})`"></span>
        </button>
      </div>

      <div x-show="tab === 'fields'">
        @include('backend.partials.screens.form.fields')
      </div>

      <div x-show="tab === 'settings'">
        @include('backend.partials.screens.form.settings')
      </div>

      <div x-show="tab === 'submissions'">
        @include('backend.partials.screens.form.submissions')
      </div>
    </div>
  </div>
</div>
</template>
