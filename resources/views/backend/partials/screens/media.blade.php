<template x-if="$store.ui.screen === 'media'">
<div x-data="media" x-init="init()" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Media library</h2>
      <div class="page-subtitle" x-text="`${folderName} — drag files anywhere to upload`"></div>
    </div>

    <button x-show="$store.auth.can('media.create')" class="btn btn-primary"
            @click="$refs.upload.click()" :disabled="uploading">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 19V5"></path><path d="m5 12 7-7 7 7"></path>
      </svg>
      <span x-text="uploading ? 'Uploading…' : 'Upload'"></span>
    </button>
    <input type="file" multiple class="hidden" x-ref="upload" @change="pickFiles($event)">
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div class="flex flex-col gap-5 lg:flex-row">
    @include('backend.partials.screens.media.folders')
    @include('backend.partials.screens.media.grid')
  </div>

  @include('backend.partials.screens.media.details')
</div>
</template>
