{{-- Rendered once for the whole panel; any screen can open it via $store.picker --}}
<div x-data="mediaPicker" x-effect="$store.picker.open && openIfNeeded()">
  <template x-if="$store.picker.open">
    <div class="modal-backdrop !z-[60]" @click.self="cancel()" @keydown.escape.window="cancel()">
      <div class="modal w-[min(900px,100%)] !max-h-[88vh]"
           @dragover.prevent="dragging = true"
           @dragleave.prevent="dragging = false"
           @drop.prevent="onDropFiles($event)">

        <div class="modal-head">
          <span class="h-2.5 w-2.5 bg-accent"></span>
          <span x-text="$store.picker.title"></span>
          <button class="ml-auto text-white/70 hover:text-white" @click="cancel()" title="Close">✕</button>
        </div>

        <div class="toolbar !py-2">
          <select class="input !h-[30px] w-[150px] !text-xs" @change="setFolder($event.target.value)">
            <option value="all">All files</option>
            <option value="unfiled">Unfiled</option>
            <template x-for="folder in folders" :key="folder.id">
              <option :value="folder.id" x-text="folder.name"></option>
            </template>
          </select>

          <div class="relative ml-auto flex-[0_1_220px] min-w-[150px]">
            <input class="input !h-[30px] !text-xs" placeholder="Search files…"
                   x-model="query.search" @input="searchDebounced()">
          </div>

          <button class="btn btn-secondary btn-sm" @click="$refs.pickerUpload.click()" :disabled="uploading">
            <span x-text="uploading ? 'Uploading…' : 'Upload new'"></span>
          </button>
          <input type="file" multiple class="hidden" x-ref="pickerUpload" @change="pickFiles($event)">
        </div>

        <div class="upload-list" x-show="uploads.length > 0" x-cloak>
          <template x-for="item in uploads" :key="item.name">
            <div class="upload-row">
              <span class="truncate text-[13px]" x-text="item.name"></span>
              <span class="upload-track">
                <span class="upload-fill" :class="item.status === 'failed' && 'is-failed'" :style="`width:${item.percent}%`"></span>
              </span>
              <span class="text-[11px]" :class="item.status === 'failed' ? 'text-accent-700' : 'text-neutral-600'"
                    x-text="item.status === 'failed' ? item.message : `${item.percent}%`"></span>
            </div>
          </template>
        </div>

        <div class="drop-hint" x-show="dragging" x-cloak>Drop to upload and use</div>

        <div class="modal-body">
          <template x-if="error">
            <div class="alert" x-text="error"></div>
          </template>

          <div x-show="loading" class="table-empty">Loading files…</div>
          <div x-show="isEmpty" class="table-empty">No files here. Upload one to get started.</div>

          <div class="media-grid" x-show="!loading && files.length > 0">
            <template x-for="file in files" :key="file.id">
              <button class="media-card media-card-pick" @click="choose(file)" :title="file.name">
                <span class="media-thumb">
                  <template x-if="file.is_image">
                    <img :src="file.url" :alt="file.alt_text ?? file.name" loading="lazy">
                  </template>
                  <template x-if="!file.is_image">
                    <span class="media-icon" x-text="iconFor(file.kind)"></span>
                  </template>
                </span>
                <span class="media-meta">
                  <span class="truncate font-medium" x-text="file.name"></span>
                  <span class="text-[11px] text-neutral-500" x-text="file.size"></span>
                </span>
              </button>
            </template>
          </div>
        </div>

        <div class="modal-foot">
          <span class="pagination-count mr-auto" x-text="resultCount"></span>
          <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
          <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
          <button class="btn btn-secondary" @click="cancel()">Cancel</button>
        </div>
      </div>
    </div>
  </template>
</div>
