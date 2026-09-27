{{-- "Add from library": pick a section someone already built. --}}
<template x-if="libraryOpen">
  <div class="modal-backdrop" @click.self="libraryOpen = false" @keydown.escape.window="libraryOpen = false">
    <div class="modal w-[min(760px,100%)] !max-h-[85vh]">

      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span>Add a section from the library</span>
        <button class="ml-auto text-white/70 hover:text-white" @click="libraryOpen = false" title="Close">✕</button>
      </div>

      <div class="toolbar !py-2">
        <input class="input !h-[30px] !text-xs flex-[0_1_260px]" placeholder="Search the library…"
               x-model="librarySearch">
        <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="$store.ui.go('sections')">
          Build a new one ↗
        </button>
      </div>

      <div class="modal-body">
        <div class="template-grid">
          <template x-for="template in visibleLibrary" :key="template.id">
            <button type="button" class="template-card" @click="addFromLibrary(template)">
              <span class="template-name" x-text="template.name"></span>
              <span class="template-summary" x-text="template.description || 'No description.'"></span>
              <span class="template-count" x-text="fieldSummary(template)"></span>
            </button>
          </template>
        </div>

        <div x-show="visibleLibrary.length === 0" class="table-empty">
          <span x-show="librarySearch">Nothing in the library matches that.</span>
          <span x-show="!librarySearch">The library is empty. Build a section first.</span>
        </div>
      </div>
    </div>
  </div>
</template>
