<template x-if="details">
  <div class="modal-backdrop" @click.self="closeDetails()" @keydown.escape.window="closeDetails()">
    <div class="modal w-[min(760px,100%)]">

      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        File details
      </div>

      <div class="modal-body !flex-row !flex-wrap !gap-6">

        {{-- preview --}}
        <div class="min-w-[220px] flex-1">
          <div class="details-preview">
            <template x-if="details.is_image">
              <img :src="details.url" :alt="details.alt_text ?? details.name">
            </template>
            <template x-if="!details.is_image">
              <span class="media-icon !text-[28px]" x-text="iconFor(details.kind)"></span>
            </template>
          </div>

          <dl class="details-facts">
            <div><dt>Type</dt><dd x-text="details.mime_type"></dd></div>
            <div><dt>Size</dt><dd x-text="details.size"></dd></div>
            <div><dt>Uploaded</dt><dd x-text="details.uploaded"></dd></div>
            <div x-show="details.uploaded_by"><dt>By</dt><dd x-text="details.uploaded_by"></dd></div>
          </dl>

          <div class="mt-3 flex gap-2">
            <button class="btn btn-secondary btn-sm flex-1" @click="copyLink(details)"
                    x-text="copied === details.id ? 'Copied' : 'Copy link'"></button>
            <a class="btn btn-secondary btn-sm" :href="details.url" target="_blank" rel="noopener">Open ↗</a>
          </div>
        </div>

        {{-- editable fields --}}
        <div class="min-w-[260px] flex-[1.3] flex flex-col gap-4">
          <div class="field">
            <label>File name</label>
            <input class="input" :class="detailsError('name') && 'input-invalid'" x-model="details.name">
            <p class="field-error" x-show="detailsError('name')" x-text="detailsError('name')"></p>
          </div>

          <div class="field">
            <label>Alt text <span class="font-normal text-neutral-500">— describes the image for search engines and screen readers</span></label>
            <input class="input" :class="detailsError('alt_text') && 'input-invalid'" x-model="details.alt_text">
            <p class="field-error" x-show="detailsError('alt_text')" x-text="detailsError('alt_text')"></p>
          </div>

          <div class="field">
            <label>Caption</label>
            <textarea class="input !min-h-[70px]" x-model="details.caption"></textarea>
            <p class="field-error" x-show="detailsError('caption')" x-text="detailsError('caption')"></p>
          </div>

          <div class="field">
            <label>Folder</label>
            <select class="input" x-model="details.folder_id">
              <option :value="null">Unfiled</option>
              <template x-for="folder in folders" :key="folder.id">
                <option :value="folder.id" x-text="folder.name"></option>
              </template>
            </select>
          </div>

          <div class="field">
            <label>Public link</label>
            <input class="input font-mono text-[12px]" :value="details.url" readonly
                   @focus="$event.target.select()">
          </div>

          <div x-show="details.usage && details.usage.length > 0">
            <div class="mb-1.5 text-[12px] font-semibold text-neutral-700">Used by</div>
            <template x-for="use in details.usage" :key="use.type + use.id">
              <div class="usage-row">
                <span class="truncate" x-text="use.title"></span>
                <span class="ml-auto text-[11px] text-neutral-500" x-text="use.type"></span>
              </div>
            </template>
          </div>

          <p x-show="details.usage && details.usage.length === 0" class="text-[12px] text-neutral-500">
            Not used by any post yet.
          </p>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-ghost mr-auto text-accent-700" x-show="$store.auth.can('media.delete')"
                @click="askDelete(details)">Delete file</button>
        <button class="btn btn-secondary" @click="closeDetails()">Close</button>
        <button class="btn btn-primary" @click="saveDetails()" :disabled="detailsSaving"
                x-show="$store.auth.can('media.update')"
                x-text="detailsSaving ? 'Saving…' : 'Save changes'"></button>
      </div>
    </div>
  </div>
</template>

{{-- delete confirmation, shared by the single and bulk paths --}}
<template x-if="confirming">
  <div class="modal-backdrop !z-50" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
    <div class="modal w-[min(460px,100%)]">
      <div class="modal-head"><span class="h-2.5 w-2.5 bg-accent"></span> Move to trash</div>
      <div class="modal-body">
        <p class="text-sm leading-relaxed text-neutral-700">
          Move <strong class="text-ink" x-text="confirming.length === 1 ? confirming[0].name : `${confirming.length} files`"></strong>
          to the trash? They leave the library now and can be restored from <strong class="text-ink">Trash</strong> for 30 days.
        </p>
        <p class="alert" x-show="confirming.some(f => (f.usage ?? []).length > 0)">
          One of these is used by a post. It keeps showing there while it is in the trash, but deleting it for good will break it.
        </p>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
        <button class="btn btn-primary" @click="confirmDelete()">Move to trash</button>
      </div>
    </div>
  </div>
</template>
