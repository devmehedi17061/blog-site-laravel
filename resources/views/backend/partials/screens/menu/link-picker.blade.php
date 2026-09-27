{{-- Somewhere to link to, so nobody has to remember a slug. --}}
<template x-if="linkPickerFor !== null">
  <div class="modal-backdrop" @click.self="linkPickerFor = null" @keydown.escape.window="linkPickerFor = null">
    <div class="modal w-[min(520px,100%)] !max-h-[80vh]">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span>Link to</span>
        <button class="ml-auto text-white/70 hover:text-white" @click="linkPickerFor = null" title="Close">✕</button>
      </div>

      <div class="modal-body">
        <template x-for="group in options.links" :key="group.group">
          <div class="link-group">
            <div class="link-group-head" x-text="group.group"></div>

            <template x-for="link in group.items" :key="link.url">
              <button type="button" class="link-row" @click="chooseLink(link.url)">
                <span class="truncate" x-text="link.label"></span>
                <span class="link-row-url" x-text="link.url"></span>
              </button>
            </template>
          </div>
        </template>

        <p class="text-[11px] text-neutral-500">
          Anything else — an external site, an anchor — can be typed straight into the Link box.
        </p>
      </div>
    </div>
  </div>
</template>
