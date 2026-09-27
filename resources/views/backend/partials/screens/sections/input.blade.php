{{-- One value input, chosen by the field's type.

     Included twice: once for a section's own fields and once for the fields
     inside a repeated group, so both are edited exactly the same way.

     $target — a JS expression for the object holding the value
     $field  — a JS expression for the field definition --}}

<template x-if="{{ $field }}.type === 'text'">
  <input class="input" x-model="{{ $target }}[{{ $field }}.key]" @input="touch()"
         :placeholder="{{ $field }}.label">
</template>

<template x-if="{{ $field }}.type === 'select'">
  <select class="input" x-model="{{ $target }}[{{ $field }}.key]" @change="touch()">
    <template x-for="option in String({{ $field }}.options || 'yes, no').split(',').map(o => o.trim()).filter(Boolean)" :key="option">
      <option :value="option" x-text="option" :selected="option === {{ $target }}[{{ $field }}.key]"></option>
    </template>
  </select>
</template>

<template x-if="{{ $field }}.type === 'url'">
  <input class="input font-mono !text-[13px]" x-model="{{ $target }}[{{ $field }}.key]" @input="touch()"
         placeholder="/contact, https://… or mailto:…">
</template>

<template x-if="{{ $field }}.type === 'media'">
  <div class="media-value">
    <img class="media-value-thumb" x-show="{{ $target }}[{{ $field }}.key]"
         :src="{{ $target }}[{{ $field }}.key]" alt="">

    <input class="input font-mono !text-[12px] min-w-0 flex-1" x-model="{{ $target }}[{{ $field }}.key]"
           @input="touch()" placeholder="Choose from the library, or paste a URL">

    <button type="button" class="btn btn-secondary btn-sm"
            @click="pickMedia({{ $target }}, {{ $field }}.key)">Choose</button>

    <button type="button" class="btn btn-ghost btn-sm !px-2" x-show="{{ $target }}[{{ $field }}.key]"
            @click="clearMedia({{ $target }}, {{ $field }}.key)">Clear</button>
  </div>
</template>

<template x-if="{{ $field }}.type === 'form'">
  <div class="flex flex-wrap items-center gap-2">
    <select class="input min-w-0 flex-1" x-model="{{ $target }}[{{ $field }}.key]" @change="touch()">
      <option value="">No form</option>
      <template x-for="option in formsList" :key="option.id">
        <option :value="String(option.id)" x-text="`${option.name} (${option.fields_count ?? 0} fields)`"></option>
      </template>
    </select>

    <button type="button" class="btn btn-ghost btn-sm !px-2"
            @click="$store.ui.go('forms')">Form builder ↗</button>

    <p class="w-full text-[11px] text-neutral-500" x-show="formsLoaded && formsList.length === 0">
      No active forms yet. Build one in the form builder, then choose it here.
    </p>
  </div>
</template>

<template x-if="{{ $field }}.type === 'richtext'">
  <div class="field-editor"
       x-data="fieldEditor(
         () => {{ $target }}[{{ $field }}.key],
         (html) => setValue({{ $target }}, {{ $field }}.key, html)
       )">
    <div class="field-editor-bar">
      <button type="button" :class="active.bold && 'is-on'" @click="toggle('bold')" title="Bold"><b>B</b></button>
      <button type="button" :class="active.italic && 'is-on'" @click="toggle('italic')" title="Italic"><i>I</i></button>
      <button type="button" :class="active.strike && 'is-on'" @click="toggle('strike')" title="Strikethrough"><s>S</s></button>
      <span class="field-editor-sep"></span>
      <button type="button" :class="active.h3 && 'is-on'" @click="toggle('h3')" title="Heading">H</button>
      <button type="button" :class="active.bullet && 'is-on'" @click="toggle('bullet')" title="Bulleted list">•</button>
      <button type="button" :class="active.ordered && 'is-on'" @click="toggle('ordered')" title="Numbered list">1.</button>
      <span class="field-editor-sep"></span>
      <button type="button" :class="active.link && 'is-on'" @click="setLink()" title="Link">↗</button>
    </div>

    <div x-ref="surface"></div>
  </div>
</template>
