{{-- Every section in the library, with how many page sections use it. --}}
<div class="builder-col builder-col-list">
  <div class="builder-col-head">Library</div>

  <div class="flex-none border-b border-line p-2">
    <input class="input !h-[30px] !text-xs" placeholder="Search sections…" x-model="search">
  </div>

  <div class="min-h-0 flex-1 overflow-y-auto">
    <template x-for="template in visibleTemplates" :key="template.id">
      <div class="section-row" :class="isActive(template) && 'section-row-active'">
        <button type="button" class="section-row-main" @click="select(template)">
          <span class="block truncate font-semibold" x-text="template.name"></span>
          <span class="block truncate text-[11px] text-neutral-600" x-text="usageLabel(template)"></span>
        </button>

        <div class="section-row-actions">
          <button type="button" title="Duplicate" @click.stop="duplicate(template)">⧉</button>
          <button type="button" title="Delete" @click.stop="remove(template)">✕</button>
        </div>
      </div>
    </template>

    <div x-show="visibleTemplates.length === 0" class="p-4 text-[13px] text-neutral-600">
      <span x-show="search">Nothing matches that search.</span>
      <span x-show="!search">No sections yet. Build the first one.</span>
    </div>
  </div>
</div>
