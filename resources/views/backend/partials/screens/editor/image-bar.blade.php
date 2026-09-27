{{-- Appears only while an image is selected in the editor. --}}
<div class="image-bar" x-show="active.image" x-cloak>

  <span class="image-bar-label">Image</span>

  <div class="flex items-center gap-1">
    <span class="toolbar-label">Size</span>
    <template x-for="option in imageSizes" :key="option.value">
      <button type="button" class="tb-btn"
              :class="active.image?.size === option.value && 'tb-btn-active'"
              :title="`${option.label} — ${option.hint}`"
              @click="setImageAttr('size', option.value)"
              x-text="option.label"></button>
    </template>
  </div>

  <span class="tb-sep"></span>

  <div class="flex items-center gap-1">
    <span class="toolbar-label">Align</span>
    <template x-for="option in imageAligns" :key="option.value">
      <button type="button" class="tb-btn"
              :class="active.image?.align === option.value && 'tb-btn-active'"
              :title="option.label"
              @click="setImageAttr('align', option.value)"
              x-text="option.label"></button>
    </template>
  </div>

  <span class="tb-sep"></span>

  <button type="button" class="tb-btn !min-w-0 px-2"
          :class="!active.image?.alt && 'tb-btn-warn'"
          @click="editImageAlt()"
          :title="active.image?.alt ? `Alt text: ${active.image?.alt}` : 'No alt text yet — search engines and screen readers need it'">
    <span x-text="active.image?.alt ? 'Alt ✓' : 'Alt !'"></span>
  </button>

  <button type="button" class="tb-btn !min-w-0 px-2" @click="editImageCaption()"
          :class="active.image?.caption && 'tb-btn-active'"
          title="Caption shown under the image">
    Caption
  </button>

  <button type="button" class="tb-btn !min-w-0 ml-auto px-2 text-accent-700" @click="removeImage()"
          title="Remove this image">
    Remove
  </button>
</div>
