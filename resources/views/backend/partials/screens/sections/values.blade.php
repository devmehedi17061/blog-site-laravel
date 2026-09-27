{{-- What this page writes into the library section's fields. --}}
<div class="flex flex-col gap-4 overflow-y-auto p-3" x-show="pane === 'content' && isLibrarySection">

  <template x-if="!activeSection.template">
    <div class="alert">
      The library section behind this one was deleted, so there is nothing to render.
      Remove it from the page, or add a replacement from the library.
    </div>
  </template>

  <template x-for="(field, fieldIndex) in activeFields" :key="fieldIndex">
    <div>
      {{-- a plain field --}}
      <template x-if="field.type !== 'group'">
        <div class="field">
          <label x-text="field.label"></label>
          @include('backend.partials.screens.sections.input', [
            'target' => 'activeSection.values',
            'field' => 'field',
          ])
        </div>
      </template>

      {{-- a repeatable group --}}
      <template x-if="field.type === 'group'">
        <div class="group-box">
          <div class="group-box-head">
            <span class="group-box-title" x-text="field.label"></span>
            <span class="counter"
                  x-text="`${activeSection.values[field.key].length} item${activeSection.values[field.key].length === 1 ? '' : 's'}`"></span>
            <button type="button" class="btn btn-secondary btn-sm ml-auto"
                    @click="addItem(activeSection, field)">+ Add item</button>
          </div>

          <template x-for="(item, itemIndex) in activeSection.values[field.key]" :key="itemIndex">
            <div class="group-item">
              <div class="group-item-head">
                <span class="truncate font-semibold" x-text="itemLabel(field, item, itemIndex)"></span>

                <div class="section-row-actions ml-auto">
                  <button type="button" title="Move up" @click="moveItem(activeSection, field, itemIndex, -1)"
                          :disabled="itemIndex === 0">↑</button>
                  <button type="button" title="Move down" @click="moveItem(activeSection, field, itemIndex, 1)"
                          :disabled="itemIndex === activeSection.values[field.key].length - 1">↓</button>
                  <button type="button" title="Duplicate this item"
                          @click="duplicateItem(activeSection, field, itemIndex)">⧉</button>
                  <button type="button" title="Remove this item"
                          @click="removeItem(activeSection, field, itemIndex)">✕</button>
                </div>
              </div>

              <div class="group-item-body">
                <template x-for="(sub, subIndex) in field.fields" :key="subIndex">
                  <div class="field">
                    <label x-text="sub.label"></label>
                    @include('backend.partials.screens.sections.input', [
                      'target' => 'item',
                      'field' => 'sub',
                    ])
                  </div>
                </template>
              </div>
            </div>
          </template>

          <div x-show="activeSection.values[field.key].length === 0"
               class="p-3 text-[13px] text-neutral-600">
            No items yet. Add one and it repeats on the page.
          </div>
        </div>
      </template>
    </div>
  </template>

  <div x-show="activeSection.template && activeFields.length === 0"
       class="p-3 text-[13px] text-neutral-600">
    This library section has no fields — it renders the same on every page.
  </div>
</div>
