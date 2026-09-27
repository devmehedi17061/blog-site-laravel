{{-- The field tree. A group holds its own fields and repeats on the page. --}}
<div class="flex flex-col gap-3 overflow-y-auto p-3" x-show="pane === 'fields'">

  {{-- Fold every card to a one-line summary, to see the whole list at once. --}}
  <div class="flex items-center gap-2" x-show="fields.length > 1">
    <span class="text-[12px] text-neutral-600" x-text="`${fields.length} fields · ${collapsed.length} collapsed`"></span>
    <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="expandAll()" :disabled="collapsed.length === 0">Expand all</button>
    <button type="button" class="btn btn-ghost btn-sm" @click="collapseAll()" :disabled="allCollapsed">Collapse all</button>
  </div>

  <template x-for="(field, index) in fields" :key="index">
    <div class="field-card" :class="isCollapsed(field) && 'is-collapsed'">
      <div class="field-card-head">
        <button type="button" class="field-card-toggle" @click="toggleField(field)"
                :aria-expanded="!isCollapsed(field)"
                :title="isCollapsed(field) ? 'Expand this field' : 'Collapse this field'">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
          <span class="sr-only" x-text="isCollapsed(field) ? 'Expand' : 'Collapse'"></span>
        </button>

        <button type="button" class="token token-button" x-show="field.type !== 'group'"
                title="Copy this token" @click="copy(tokenFor(field.key))" x-text="tokenFor(field.key)"></button>

        <button type="button" class="token token-button" x-show="field.type === 'group'"
                title="Copy the repeat wrapper"
                @click="copy(groupOpen(field.key) + groupClose(field.key))"
                x-text="groupOpen(field.key) + ' … ' + groupClose(field.key)"></button>

        {{-- Folded: say what the field is, and flag a problem inside it. --}}
        <button type="button" class="field-card-summary" x-show="isCollapsed(field)" @click="toggleField(field)">
          <span class="truncate" x-text="fieldSummary(field)"></span>
          <span class="field-card-alert" x-show="fieldWarning(field)" :title="fieldWarning(field)">!</span>
        </button>

        <div class="ml-auto flex gap-0.5">
          <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move up"
                  @click="moveField(index, -1)" :disabled="index === 0">↑</button>
          <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move down"
                  @click="moveField(index, 1)" :disabled="index === fields.length - 1">↓</button>
          <button type="button" class="btn btn-ghost btn-sm !px-2 text-accent-700"
                  @click="removeField(index)">Remove</button>
        </div>
      </div>

      <div class="flex flex-wrap gap-3 p-3" x-show="!isCollapsed(field)">
        <div class="field flex-[1_1_130px]">
          <label>Key</label>
          <input class="input font-mono !text-[13px]" x-model="field.key"
                 @input="onKeyInput(field)" @blur="onKeyBlur(field, fields)">
        </div>

        <div class="field flex-[1_1_130px]">
          <label>Label</label>
          <input class="input" x-model="field.label" @input="touch()">
        </div>

        <div class="field flex-[1_1_130px]">
          <label>Type</label>
          <select class="input" x-model="field.type" @change="onTypeChange(field)">
            <template x-for="type in allFieldTypes" :key="type">
              <option :value="type" x-text="fieldTypes[type]?.label ?? type"></option>
            </template>
          </select>
        </div>

        <div class="field flex-[2_1_220px]" x-show="field.type === 'select'">
          <label>Options <span class="font-normal text-neutral-500">— separated by commas</span></label>
          <input class="input" x-model="field.options" @input="touch()" placeholder="yes, no">
        </div>

        <div class="field flex-[2_1_220px]" x-show="field.type !== 'group'">
          <label>Default <span class="font-normal text-neutral-500">— what a page starts with</span></label>
          <template x-if="field.type === 'select'">
            <select class="input" x-model="field.default" @change="touch()">
              <template x-for="option in String(field.options || 'yes, no').split(',').map(o => o.trim()).filter(Boolean)" :key="option">
                <option :value="option" x-text="option" :selected="option === field.default"></option>
              </template>
            </select>
          </template>
          <template x-if="field.type !== 'select'">
            <input class="input" x-model="field.default" @input="touch()" placeholder="Left blank on a new page">
          </template>
        </div>

        <p class="w-full text-[11px] text-neutral-500" x-text="fieldTypes[field.type]?.hint"></p>

        {{-- Said here, on the field itself, rather than only under the HTML
             box: a field whose token is missing or pasted twice looks fine
             in the builder and wrong on the site. --}}
        <p class="field-warning" x-show="fieldWarning(field)" x-text="fieldWarning(field)"></p>

        {{-- a group's own fields --}}
        <div class="w-full" x-show="field.type === 'group'">
          <div class="group-fields">
            <div class="group-fields-head">
              <span x-text="`Fields inside each ${field.label || field.key}`"></span>
              <button type="button" class="btn btn-ghost btn-sm !px-2 ml-auto"
                      @click="addSubField(field)">+ Add field</button>
            </div>

            <template x-for="(sub, subIndex) in field.fields" :key="subIndex">
              <div class="group-field-row">
                <button type="button" class="token token-button flex-none" title="Copy this token"
                        @click="copy(tokenFor(sub.key))" x-text="tokenFor(sub.key)"></button>

                <input class="input !h-[28px] flex-[1_1_110px] font-mono !text-[12px]" x-model="sub.key"
                       @input="onKeyInput(sub)" @blur="onKeyBlur(sub, field.fields)" placeholder="key">

                <input class="input !h-[28px] flex-[1_1_110px] !text-[12px]" x-model="sub.label"
                       @input="touch()" placeholder="Label">

                <select class="input !h-[28px] flex-[0_1_110px] !text-[12px]" x-model="sub.type" @change="touch()">
                  <template x-for="type in subFieldTypes" :key="type">
                    <option :value="type" x-text="fieldTypes[type]?.label ?? type"></option>
                  </template>
                </select>

                <div class="flex flex-none gap-0.5">
                  <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move up"
                          @click="moveSubField(field, subIndex, -1)" :disabled="subIndex === 0">↑</button>
                  <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move down"
                          @click="moveSubField(field, subIndex, 1)"
                          :disabled="subIndex === field.fields.length - 1">↓</button>
                  <button type="button" class="btn btn-ghost btn-sm !px-1.5 text-accent-700"
                          @click="removeSubField(field, subIndex)">✕</button>
                </div>
              </div>
            </template>

            <p class="px-2 pb-2 text-[11px] leading-relaxed text-neutral-500">
              Wrap this part of the HTML in
              <code class="font-mono" x-text="groupOpen(field.key)"></code> …
              <code class="font-mono" x-text="groupClose(field.key)"></code>
              and it repeats once per item. A page can add as many items as it needs.
            </p>
          </div>
        </div>
      </div>
    </div>
  </template>

  <div class="flex gap-2">
    <button type="button" class="btn btn-secondary btn-sm" @click="addField('text')">+ Add field</button>
    <button type="button" class="btn btn-secondary btn-sm" @click="addField('group')">+ Add group</button>
  </div>

  <div x-show="fields.length === 0" class="p-3 text-[13px] text-neutral-600">
    No fields yet. Add one, then drop its token into the HTML.
  </div>
</div>
