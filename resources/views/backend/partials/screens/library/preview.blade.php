{{-- Rendered with stand-in values and the site's own stylesheet, so the shape
     and the design are visible before any page fills it in. The frame keeps
     the device's real width (so @media rules fire) and is scaled to fit. --}}
<div class="builder-col builder-col-preview" :class="previewLarge && 'is-large'" @keydown.escape.window="previewLarge = false">
  <div class="builder-col-head flex items-center gap-2">
    <span>Preview</span>
    <div class="lib-devices ml-auto" role="group" aria-label="Preview size">
      <template x-for="(d, key) in previewDevices" :key="key">
        <button type="button" :class="previewDevice === key && 'is-on'" @click="previewDevice = key" :aria-pressed="previewDevice === key"
                :title="d.width ? `${d.label} · ${d.width}px` : 'As wide as this column'" x-text="d.label"></button>
      </template>
    </div>
    <button type="button" class="lib-enlarge" @click="togglePreviewLarge()" :aria-pressed="previewLarge"
            :title="previewLarge ? 'Back to the column (Esc)' : 'Show the preview large'" x-text="previewLarge ? '✕ Close' : '⤢ Enlarge'"></button>
  </div>

  <div class="lib-stage min-h-0 flex-1" x-ref="previewStage" x-init="watchPreviewStage()">
    <template x-if="draft">
      <div class="lib-frame" :style="previewFrameStyle" x-live-frame="previewDoc" data-title="Section preview"></div>
    </template>

    <div x-show="!draft" class="p-4 text-[13px] text-neutral-600">Nothing to preview.</div>
  </div>

  <p class="flex-none border-t border-line p-2 text-[11px] text-neutral-500">
    <span x-text="`${Math.round(previewWidth)}px wide${previewScale < 1 ? ', shown at ' + Math.round(previewScale * 100) + '%' : ''}.`"></span>
    Shown with typical page spacing (48px 32px, content at the site width) and sample text; each page sets its own.
  </p>
</div>
