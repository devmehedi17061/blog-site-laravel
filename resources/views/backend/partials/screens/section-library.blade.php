<template x-if="$store.ui.screen === 'sections'">
<div x-data="sectionLibrary" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Builders</div>
      <h2>Section library</h2>
      <div class="page-subtitle">
        Build a section once — its fields, HTML and CSS — then add it to any page.
        Each page fills in its own words; the markup stays here.
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span class="counter" x-show="dirty">Unsaved changes</span>
      <button type="button" class="btn btn-secondary" @click="startNew()">+ New section</button>
      <button type="button" class="btn btn-primary" @click="save()" :disabled="saving || !draft"
              x-text="saving ? 'Saving…' : 'Save section'"></button>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading the library…</div>

  <div x-show="!loading" class="builder">
    <div class="builder-grid">
      @include('backend.partials.screens.library.list')
      @include('backend.partials.screens.library.editor')
      @include('backend.partials.screens.library.preview')
    </div>
  </div>
</div>
</template>
