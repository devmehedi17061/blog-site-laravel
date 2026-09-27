{{-- The fields, what each one is, and what the visitor will actually see. --}}
<div class="builder">

  <div class="builder-bar">
    <span class="token-hints-label">Add a field</span>

    <div class="type-palette">
      <template x-for="type in options.field_types" :key="type">
        <button type="button" class="type-chip" @click="addField(type)"
                :title="fieldTypes[type]?.hint">
          <span class="type-chip-mono" x-text="fieldTypes[type]?.mono ?? '?'"></span>
          <span x-text="fieldTypes[type]?.label ?? type"></span>
        </button>
      </template>
    </div>

    <span class="counter ml-auto" x-show="dirty">Unsaved changes</span>
  </div>

  <div class="builder-grid">

    {{-- the list --}}
    <div class="builder-col builder-col-list">
      <div class="builder-col-head">Fields</div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <template x-for="(field, index) in fields" :key="index">
          <div class="section-row" :class="index === activeIndex && 'section-row-active'">
            <button type="button" class="section-row-main" @click="selectField(index)">
              <span class="flex items-center gap-1.5">
                <span class="type-mono" x-text="fieldTypes[field.type]?.mono ?? '?'"></span>
                <span class="truncate font-semibold" x-text="field.label"></span>
                <span class="text-accent" x-show="field.required" title="Required">*</span>
              </span>
              <span class="block truncate text-[11px] text-neutral-600" x-text="fieldSummary(field)"></span>
              <span class="block truncate text-[11px] text-accent-700" x-show="errorForField(index)"
                    x-text="errorForField(index)"></span>
            </button>

            <div class="section-row-actions">
              <button type="button" title="Move up" @click.stop="moveField(index, -1)" :disabled="index === 0">↑</button>
              <button type="button" title="Move down" @click.stop="moveField(index, 1)"
                      :disabled="index === fields.length - 1">↓</button>
              <button type="button" title="Duplicate" @click.stop="duplicateField(index)">⧉</button>
              <button type="button" title="Delete" @click.stop="removeField(index)">✕</button>
            </div>
          </div>
        </template>

        <div x-show="fields.length === 0" class="p-4 text-[13px] text-neutral-600">
          No fields yet. Pick a type above to add the first one.
        </div>
      </div>
    </div>

    {{-- the editor --}}
    <div class="builder-col builder-col-edit">
      <template x-if="!activeField">
        <div class="p-5 text-[13px] text-neutral-600">Add a field, or pick one on the left to edit it.</div>
      </template>

      <template x-if="activeField">
        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto p-3">
          <div class="flex flex-wrap gap-3">
            <div class="field flex-[2_1_200px]">
              <label>Label <span class="font-normal text-neutral-500">— what the visitor reads</span></label>
              <input class="input" x-model="activeField.label" @input="onLabelInput(activeField)">
            </div>

            <div class="field flex-[1_1_140px]">
              <label>Type</label>
              <select class="input" x-model="activeField.type" @change="onTypeChange(activeField)">
                <template x-for="type in options.field_types" :key="type">
                  <option :value="type" x-text="fieldTypes[type]?.label ?? type"></option>
                </template>
              </select>
            </div>

            <div class="field flex-[1_1_120px]">
              <label>Width</label>
              <select class="input" x-model="activeField.width" @change="touch()">
                <option value="full">Full row</option>
                <option value="half">Half row</option>
              </select>
            </div>

            <div class="field flex-[1_1_160px]">
              <label>
                Name
                <span class="font-normal text-neutral-500">— the key in every submission</span>
              </label>
              <input class="input font-mono !text-[13px]" x-model="activeField.name"
                     @input="onNameInput(activeField)" @blur="onNameBlur(activeField)">
              <p class="mt-1 text-[11px] text-neutral-500" x-show="activeField.id">
                Renaming this leaves answers already collected under the old name.
              </p>
            </div>

            <div class="field flex-[2_1_200px]" x-show="takesPlaceholder(activeField)">
              <label>Placeholder</label>
              <input class="input" x-model="activeField.placeholder" @input="touch()">
            </div>

            <div class="field w-full">
              <label>Help text <span class="font-normal text-neutral-500">— a line under the input</span></label>
              <input class="input" x-model="activeField.help" @input="touch()">
            </div>

            <p class="w-full text-[11px] text-neutral-500" x-text="fieldTypes[activeField.type]?.hint"></p>

            {{-- options --}}
            <div class="w-full" x-show="hasChoices(activeField)">
              <div class="group-fields">
                <div class="group-fields-head">
                  <span>Options</span>
                  <span class="font-normal text-neutral-500" x-show="activeField.type === 'checkbox'">
                    — leave empty for a single yes/no box
                  </span>
                  <button type="button" class="btn btn-ghost btn-sm !px-2 ml-auto"
                          @click="addOption(activeField)">+ Add option</button>
                </div>

                <template x-for="(option, optionIndex) in activeField.options" :key="optionIndex">
                  <div class="group-field-row">
                    <input class="input !h-[28px] flex-1 !text-[12px]"
                           :value="option"
                           @input="activeField.options[optionIndex] = $event.target.value; touch()"
                           placeholder="Option text">

                    <div class="flex flex-none gap-0.5">
                      <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move up"
                              @click="moveOption(activeField, optionIndex, -1)" :disabled="optionIndex === 0">↑</button>
                      <button type="button" class="btn btn-ghost btn-sm !px-1.5" title="Move down"
                              @click="moveOption(activeField, optionIndex, 1)"
                              :disabled="optionIndex === activeField.options.length - 1">↓</button>
                      <button type="button" class="btn btn-ghost btn-sm !px-1.5 text-accent-700"
                              @click="removeOption(activeField, optionIndex)">✕</button>
                    </div>
                  </div>
                </template>

                <div x-show="activeField.options.length === 0" class="p-2 text-[12px] text-neutral-600">
                  No options — this renders as a single checkbox.
                </div>
              </div>
            </div>

            {{-- validation --}}
            <div class="w-full border-t border-line pt-3">
              <label class="check mb-2">
                <input type="checkbox" x-model="activeField.required" @change="touch()">
                <span>Required</span>
              </label>

              <div class="field">
                <label>
                  Extra rules
                  <span class="font-normal text-neutral-500">— Laravel rules, separated by |</span>
                </label>
                <input class="input font-mono !text-[13px]" x-model="activeField.rule" @input="touch()"
                       placeholder="min:10|max:500">
                <p class="mt-1 text-[11px] leading-relaxed text-neutral-500">
                  Checked on the server, on top of the type's own rule. Anything that could reach the
                  database — <code class="font-mono">exists</code>, <code class="font-mono">unique</code> — is ignored.
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    {{-- preview --}}
    <div class="builder-col builder-col-preview">
      <div class="builder-col-head">What the visitor sees</div>

      <div class="min-h-0 flex-1 overflow-y-auto p-4">
        @include('backend.partials.screens.form.preview')
      </div>
    </div>
  </div>
</div>
