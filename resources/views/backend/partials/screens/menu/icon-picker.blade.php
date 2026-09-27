{{-- The icons the site can actually draw, from the same map it draws them
     with — so nothing can be picked here that the header would refuse. --}}
<template x-if="iconPickerFor !== null">
  <div class="modal-backdrop" @click.self="iconPickerFor = null" @keydown.escape.window="iconPickerFor = null">
    <div class="modal w-[min(620px,100%)] !max-h-[80vh]">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span>Choose an icon</span>
        <button class="ml-auto text-white/70 hover:text-white" @click="iconPickerFor = null" title="Close">✕</button>
      </div>

      <div class="toolbar !py-2">
        <input class="input !h-[30px] !text-xs flex-[0_1_240px]" placeholder="Search icons…"
               x-model="iconSearch" autofocus>
        <span class="ml-auto text-[12px] text-neutral-600" x-text="`${iconNames.length} icons`"></span>
      </div>

      <div class="modal-body">
        <div class="icon-grid">
          <template x-for="name in iconNames" :key="name">
            <button type="button" class="icon-cell" :title="name" @click="chooseIcon(name)">
              <span x-html="iconSvg(name, 20)"></span>
              <span class="icon-cell-name" x-text="name"></span>
            </button>
          </template>
        </div>

        <div x-show="iconNames.length === 0" class="table-empty">No icon matches that.</div>
      </div>
    </div>
  </div>
</template>
