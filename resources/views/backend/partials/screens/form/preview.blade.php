{{-- The real thing, not a drawing of it: these are the same `.cms-*` classes
     the public form uses, from the same stylesheet. Inputs are disabled so
     the preview cannot be typed into by mistake. --}}
<div class="cms-form form-preview">
  <div class="cms-form-grid">
    <template x-for="(field, index) in fields" :key="index">
      <div class="cms-field" :class="field.width === 'half' ? 'is-half' : 'is-full'">

        <template x-if="isToggle(field)">
          <label class="cms-check">
            <input type="checkbox" disabled>
            <span>
              <span x-text="field.label"></span>
              <span class="cms-required" x-show="field.required">*</span>
            </span>
          </label>
        </template>

        <template x-if="!isToggle(field)">
          <label class="cms-label">
            <span x-text="field.label"></span>
            <span class="cms-required" x-show="field.required">*</span>
          </label>
        </template>

        <template x-if="field.type === 'textarea'">
          <textarea class="cms-input" rows="4" disabled :placeholder="field.placeholder"></textarea>
        </template>

        <template x-if="field.type === 'select'">
          <select class="cms-input" disabled>
            <option x-text="field.placeholder || 'Choose…'"></option>
          </select>
        </template>

        <template x-if="field.type === 'radio'">
          <div class="cms-choices">
            <template x-for="(option, i) in field.options" :key="i">
              <label class="cms-check">
                <input type="radio" disabled>
                <span x-text="option || 'Untitled option'"></span>
              </label>
            </template>
          </div>
        </template>

        <template x-if="field.type === 'checkbox' && !isToggle(field)">
          <div class="cms-choices">
            <template x-for="(option, i) in field.options" :key="i">
              <label class="cms-check">
                <input type="checkbox" disabled>
                <span x-text="option || 'Untitled option'"></span>
              </label>
            </template>
          </div>
        </template>

        <template x-if="['text', 'email', 'tel', 'url', 'number', 'date', 'file'].includes(field.type)">
          <input class="cms-input" :class="field.type === 'file' && 'cms-file'"
                 :type="field.type" disabled :placeholder="field.placeholder">
        </template>

        <template x-if="field.help">
          <p class="cms-help" x-text="field.help"></p>
        </template>
      </div>
    </template>
  </div>

  <div x-show="fields.length === 0" class="text-[13px] text-neutral-600">
    Nothing to show yet — add a field.
  </div>

  <button type="button" class="cms-form-submit" disabled
          x-text="draft.submit_label || 'Submit'" x-show="fields.length > 0"></button>
</div>
