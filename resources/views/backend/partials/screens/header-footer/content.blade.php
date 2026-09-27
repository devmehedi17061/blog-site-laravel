{{-- The Content tab: what this header or footer says, field by field. The
     whole site shares it, so the values live here rather than on a page. --}}
<div class="flex flex-col gap-4 overflow-y-auto p-3" x-show="pane === 'content'">

  <template x-for="(field, fieldIndex) in contentFields" :key="fieldIndex">
    <div>
      <template x-if="field.type !== 'group'">
        <div class="field">
          <label x-text="field.label || field.key"></label>
          @include('backend.partials.screens.sections.input', ['target' => 'draft.values', 'field' => 'field'])
        </div>
      </template>

      <template x-if="field.type === 'group'">
        <div class="group-box">
          <div class="group-box-head">
            <span class="group-box-title" x-text="field.label || field.key"></span>
            <span class="counter" x-text="`${(draft.values[field.key] ?? []).length} item(s)`"></span>
            <button type="button" class="btn btn-secondary btn-sm ml-auto" @click="addItem(field)">+ Add item</button>
          </div>

          <template x-for="(item, itemIndex) in (draft.values[field.key] ?? [])" :key="itemIndex">
            <div class="group-item">
              <div class="group-item-head">
                <span class="truncate font-semibold" x-text="itemLabel(field, item, itemIndex)"></span>
                <div class="section-row-actions ml-auto">
                  <button type="button" title="Move up" @click="moveItem(field, itemIndex, -1)" :disabled="itemIndex === 0">↑</button>
                  <button type="button" title="Move down" @click="moveItem(field, itemIndex, 1)" :disabled="itemIndex === draft.values[field.key].length - 1">↓</button>
                  <button type="button" title="Duplicate this item" @click="duplicateItem(field, itemIndex)">⧉</button>
                  <button type="button" title="Remove this item" @click="removeItem(field, itemIndex)">✕</button>
                </div>
              </div>
              <div class="group-item-body">
                <template x-for="(sub, subIndex) in field.fields" :key="subIndex">
                  <div class="field">
                    <label x-text="sub.label || sub.key"></label>
                    @include('backend.partials.screens.sections.input', ['target' => 'item', 'field' => 'sub'])
                  </div>
                </template>
              </div>
            </div>
          </template>

          <div x-show="(draft.values[field.key] ?? []).length === 0" class="p-3 text-[13px] text-neutral-600">
            No items yet. Add one and it repeats.
          </div>
        </div>
      </template>
    </div>
  </template>

  <div x-show="contentFields.length === 0" class="hfb-empty">
    <strong x-text="`This ${noun} has no fields`"></strong>
    <span>Everything it shows comes from the HTML and the site itself (logo, menus, contact details).
      Add a field on the <button type="button" class="hfb-link" @click="pane = 'fields'">Fields</button> tab for text you want to change here — a notice, a button label, a colour.</span>
  </div>
</div>
