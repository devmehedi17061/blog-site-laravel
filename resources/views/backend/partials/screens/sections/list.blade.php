{{-- The order the page renders in. --}}
<div class="builder-col builder-col-list">
  <div class="builder-col-head">On this page</div>

  <div class="min-h-0 flex-1 overflow-y-auto">
    <template x-for="(section, index) in sections" :key="index">
      <div class="section-row" :class="index === activeIndex && 'section-row-active'">
        <button type="button" class="section-row-main" @click="selectSection(index)">
          <span class="block truncate font-semibold" x-text="section.name"></span>
          <span class="block truncate text-[11px] text-neutral-600" x-text="sectionMeta(section)"></span>
        </button>

        <div class="section-row-actions">
          <button type="button" title="Move up" @click.stop="moveSection(index, -1)" :disabled="index === 0">↑</button>
          <button type="button" title="Move down" @click.stop="moveSection(index, 1)"
                  :disabled="index === sections.length - 1">↓</button>
          <button type="button" title="Duplicate" @click.stop="duplicateSection(index)">⧉</button>
          <button type="button" title="Remove from this page" @click.stop="removeSection(index)">✕</button>
        </div>
      </div>
    </template>

    <div x-show="sections.length === 0" class="p-4 text-[13px] text-neutral-600">
      No sections yet. Add one from the library, or write a blank one.
    </div>
  </div>
</div>
