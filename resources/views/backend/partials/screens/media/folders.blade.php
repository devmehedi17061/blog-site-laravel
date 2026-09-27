<aside class="w-full flex-none lg:w-[220px]">
  <div class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Folders</div>

    <div class="flex flex-col py-1">
      <button class="folder-item" :class="isFolder('all') && 'folder-item-active'" @click="setFolder('all')">
        <span>All files</span>
        <span class="folder-count" x-text="folderMeta.total"></span>
      </button>

      <button class="folder-item" :class="isFolder('unfiled') && 'folder-item-active'" @click="setFolder('unfiled')">
        <span>Unfiled</span>
        <span class="folder-count" x-text="folderMeta.unfiled"></span>
      </button>

      <template x-for="folder in folders" :key="folder.id">
        <div>
          {{-- rename in place --}}
          <template x-if="renamingFolder === folder.id">
            <div class="px-3 py-2">
              <input class="input !h-[30px] !text-xs" x-model="folderDraft"
                     @keydown.enter="saveFolder()" @keydown.escape="cancelFolder()" x-init="$el.focus()">
              <p class="field-error" x-show="folderError" x-text="folderError"></p>
              <div class="mt-1.5 flex gap-1">
                <button class="btn btn-primary btn-sm flex-1" @click="saveFolder()">Save</button>
                <button class="btn btn-secondary btn-sm" @click="cancelFolder()">Cancel</button>
              </div>
            </div>
          </template>

          <template x-if="renamingFolder !== folder.id">
            <div class="group/folder relative flex items-center">
              <button class="folder-item flex-1 [@media(hover:none)]:pr-16" :class="isFolder(folder.id) && 'folder-item-active'"
                      @click="setFolder(folder.id)">
                <span class="truncate" x-text="folder.name"></span>
                <span class="folder-count" x-text="folder.count"></span>
              </button>

              {{-- Shown on hover, on keyboard focus, and always on touch
                   screens — a phone has no hover, and would never see them. --}}
              <div class="absolute right-1 hidden gap-0.5 group-hover/folder:flex group-focus-within/folder:flex [@media(hover:none)]:flex"
                   x-show="$store.auth.can('media.update') || $store.auth.can('media.delete')">
                <button class="folder-action" title="Rename" x-show="$store.auth.can('media.update')"
                        @click.stop="startRename(folder)">✎</button>
                <button class="folder-action" title="Delete folder" x-show="$store.auth.can('media.delete')"
                        @click.stop="deleteFolder(folder)">✕</button>
              </div>
            </div>
          </template>
        </div>
      </template>

      {{-- new folder --}}
      <template x-if="renamingFolder === 'new'">
        <div class="px-3 py-2">
          <input class="input !h-[30px] !text-xs" placeholder="Folder name" x-model="folderDraft"
                 @keydown.enter="saveFolder()" @keydown.escape="cancelFolder()" x-init="$el.focus()">
          <p class="field-error" x-show="folderError" x-text="folderError"></p>
          <div class="mt-1.5 flex gap-1">
            <button class="btn btn-primary btn-sm flex-1" @click="saveFolder()">Create</button>
            <button class="btn btn-secondary btn-sm" @click="cancelFolder()">Cancel</button>
          </div>
        </div>
      </template>

      <button class="folder-item text-accent-700" x-show="renamingFolder !== 'new' && $store.auth.can('media.create')"
              @click="startNewFolder()">
        + New folder
      </button>
    </div>
  </div>
</aside>
