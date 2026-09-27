<div class="panel min-w-0 flex-1"
     @dragover.prevent="dragging = true"
     @dragleave.prevent="dragging = false"
     @drop.prevent="onDrop($event)">

  {{-- toolbar --}}
  <div class="toolbar">
    <div class="chip-group">
      <button class="chip" :class="query.kind === 'all' && 'chip-active'" @click="setKind('all')">All</button>
      <button class="chip" :class="query.kind === 'image' && 'chip-active'" @click="setKind('image')">Images</button>
      <button class="chip" :class="query.kind === 'document' && 'chip-active'" @click="setKind('document')">Documents</button>
    </div>

    <div class="ml-auto flex items-center gap-2">
      <select class="input !h-[30px] w-[140px] !text-xs"
              @change="query.sort = $event.target.value.split(':')[0]; query.direction = $event.target.value.split(':')[1]; applyFilters()">
        <option value="created_at:desc">Newest first</option>
        <option value="created_at:asc">Oldest first</option>
        <option value="name:asc">Name A–Z</option>
        <option value="size:desc">Largest first</option>
      </select>
    </div>

    <div class="relative flex-[0_1_220px] min-w-[150px]">
      <svg class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-neutral-500"
           width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path>
      </svg>
      <input class="input !h-[30px] !pl-8 !text-xs" placeholder="Search files…"
             x-model="query.search" @input="searchDebounced()">
    </div>
  </div>

  {{-- bulk bar --}}
  <div class="bulk-bar" x-show="selected.length > 0" x-cloak>
    <span class="font-heading text-xs font-bold" x-text="`${selected.length} selected`"></span>

    <button class="btn btn-secondary btn-sm" @click="selectAll()" x-show="!allSelected">Select all on page</button>
    <button class="btn btn-secondary btn-sm" @click="clearSelection()">Clear</button>

    <div class="ml-auto flex items-center gap-2">
      <span class="toolbar-label">Move to</span>
      <select class="input !h-[30px] w-[150px] !text-xs" @change="moveSelected($event.target.value); $event.target.value = ''">
        <option value="">Choose a folder…</option>
        <option value="">Unfiled</option>
        <template x-for="folder in folders" :key="folder.id">
          <option :value="folder.id" x-text="folder.name"></option>
        </template>
      </select>

      <button class="btn btn-primary btn-sm" @click="askDeleteSelected()">Delete</button>
    </div>
  </div>

  {{-- upload progress --}}
  <div class="upload-list" x-show="uploads.length > 0" x-cloak>
    <template x-for="item in uploads" :key="item.name">
      <div class="upload-row">
        <span class="truncate text-[13px]" x-text="item.name"></span>
        <span class="upload-track">
          <span class="upload-fill" :class="item.status === 'failed' && 'is-failed'"
                :style="`width:${item.percent}%`"></span>
        </span>
        <span class="text-[11px]"
              :class="item.status === 'failed' ? 'text-accent-700' : 'text-neutral-600'"
              x-text="item.status === 'failed' ? item.message : `${item.percent}%`"></span>
      </div>
    </template>
  </div>

  {{-- drop hint --}}
  <div class="drop-hint" x-show="dragging" x-cloak>Drop files to upload them here</div>

  {{-- grid --}}
  <div class="p-4">
    <div x-show="loading" class="table-empty">Loading files…</div>

    <div x-show="isEmpty" class="table-empty">
      Nothing in <span x-text="folderName"></span> yet. Drag files in, or use Upload.
    </div>

    <div class="media-grid" x-show="!loading && files.length > 0">
      <template x-for="file in files" :key="file.id">
        <div class="media-card" :class="isSelected(file.id) && 'is-selected'">

          <button class="media-thumb" @click="openDetails(file)" :title="file.name">
            <template x-if="file.is_image">
              <img :src="file.url" :alt="file.alt_text ?? file.name" loading="lazy">
            </template>
            <template x-if="!file.is_image">
              <span class="media-icon" x-text="iconFor(file.kind)"></span>
            </template>
          </button>

          <label class="media-check" @click.stop>
            <input type="checkbox" :checked="isSelected(file.id)" @change="toggle(file.id)">
          </label>

          <div class="media-meta">
            <span class="truncate font-medium" x-text="file.name"></span>
            <span class="text-[11px] text-neutral-500" x-text="`${file.size} · ${file.uploaded}`"></span>
          </div>

          <div class="media-actions">
            <button class="btn btn-secondary btn-sm flex-1" @click="copyLink(file)"
                    :class="copied === file.id && '!border-accent !text-accent-700'"
                    x-text="copied === file.id ? 'Copied' : 'Copy link'"></button>
            <button class="btn btn-secondary btn-sm" @click="openDetails(file)" title="Edit details">Edit</button>
          </div>
        </div>
      </template>
    </div>
  </div>

  {{-- pagination --}}
  <div class="pagination-bar">
    <span class="pagination-count" x-text="resultCount"></span>
    <button class="page-btn" @click="goToPage(meta.current_page - 1)" :disabled="meta.current_page <= 1">Prev</button>
    <template x-for="page in pageNumbers" :key="page">
      <button class="page-btn" :class="page === meta.current_page && 'page-btn-active'"
              @click="goToPage(page)" x-text="page"></button>
    </template>
    <button class="page-btn" @click="goToPage(meta.current_page + 1)" :disabled="meta.current_page >= meta.last_page">Next</button>
  </div>
</div>
