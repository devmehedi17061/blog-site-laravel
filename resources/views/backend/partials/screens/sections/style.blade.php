{{-- A section is two boxes: a full-width band, and the column of content
     inside it. Every setting is a free text input, so any value the CSS
     property accepts can be typed; they are written onto the section exactly
     as entered. --}}
<div class="flex flex-col gap-4 overflow-y-auto p-3" x-show="pane === 'style'">

  {{-- ── the band ──────────────────────────────────────────────────────── --}}
  <div class="style-group">
    <div class="style-group-head">
      <span class="style-group-title">Section band</span>
      <span class="style-group-hint">Runs the full width of the page</span>
    </div>

    <div class="style-group-body">
      <div class="field">
        <label>
          Background image
          <span class="font-normal text-neutral-500">— fills the whole band, edge to edge</span>
        </label>

        <div class="media-value">
          <img class="media-value-thumb" x-show="activeSection.style.background_image"
               :src="activeSection.style.background_image" alt="">

          <input class="input font-mono !text-[12px] min-w-0 flex-1"
                 x-model="activeSection.style.background_image" @input="touch()"
                 placeholder="Choose from the library, or paste a URL">

          <button type="button" class="btn btn-secondary btn-sm"
                  @click="pickMedia(activeSection.style, 'background_image')">Choose</button>

          <button type="button" class="btn btn-ghost btn-sm !px-2" x-show="activeSection.style.background_image"
                  @click="clearMedia(activeSection.style, 'background_image')">Clear</button>
        </div>
      </div>

      <div class="flex flex-wrap gap-3" x-show="activeSection.style.background_image">
        <template x-for="setting in backgroundImageFields" :key="setting.key">
          <div class="field flex-[1_1_150px]">
            <label>
              <span x-text="setting.label"></span>
              <code class="ml-1 font-mono text-[10px] whitespace-nowrap text-neutral-500"
                    x-text="setting.property"></code>
            </label>
            <input class="input !h-[32px] !text-[13px]" :placeholder="setting.placeholder"
                   x-model="activeSection.style[setting.key]" @input="touch()">
          </div>
        </template>
      </div>

      <div class="flex flex-wrap gap-3">
        <template x-for="setting in bandStyleFields" :key="setting.key">
          <div class="field flex-[1_1_175px]">
            <label>
              <span x-text="setting.label"></span>
              <code class="ml-1 font-mono text-[10px] whitespace-nowrap text-neutral-500"
                    x-text="setting.property"></code>
            </label>
            <input class="input !h-[32px] !text-[13px]" :placeholder="setting.placeholder"
                   x-model="activeSection.style[setting.key]" @input="touch()">
          </div>
        </template>
      </div>

      <div class="field">
        <label>Anything else <span class="font-normal text-neutral-500">— any CSS, one declaration per line</span></label>
        <textarea class="input !min-h-[70px] font-mono !text-[12px]" spellcheck="false"
                  x-model="activeSection.style.custom" @input="touch()"
                  placeholder="display: flex;&#10;align-items: center;&#10;min-height: 70vh;"></textarea>
      </div>
    </div>
  </div>

  {{-- ── the column ────────────────────────────────────────────────────── --}}
  <div class="style-group">
    <div class="style-group-head">
      <span class="style-group-title">Content inside it</span>
      <span class="style-group-hint">Held to a fixed width and centred</span>
    </div>

    <div class="style-group-body">
      <div class="flex flex-wrap gap-3">
        <template x-for="setting in contentStyleFields" :key="setting.key">
          <div class="field flex-[1_1_175px]">
            <label>
              <span x-text="setting.label"></span>
              <code class="ml-1 font-mono text-[10px] whitespace-nowrap text-neutral-500"
                    x-text="setting.property"></code>
            </label>
            <input class="input !h-[32px] !text-[13px]" :placeholder="setting.placeholder"
                   x-model="activeSection.style[setting.key]" @input="touch()">
          </div>
        </template>
      </div>

      <div class="field">
        <label>Anything else <span class="font-normal text-neutral-500">— lay the content out with flex or grid</span></label>
        <textarea class="input !min-h-[70px] font-mono !text-[12px]" spellcheck="false"
                  x-model="activeSection.style.content_custom" @input="touch()"
                  placeholder="display: grid;&#10;grid-template-columns: repeat(2, 1fr);&#10;gap: 32px;"></textarea>
      </div>
    </div>
  </div>

  {{-- ── visibility ────────────────────────────────────────────────────── --}}
  <div class="flex flex-wrap gap-4">
    <label class="check">
      <input type="checkbox" x-model="activeSection.style.hide_mobile" @change="touch()">
      <span>Hide on mobile <span class="text-neutral-500">(640px and under)</span></span>
    </label>

    <label class="check">
      <input type="checkbox" x-model="activeSection.style.hide_desktop" @change="touch()">
      <span>Hide on desktop <span class="text-neutral-500">(641px and up)</span></span>
    </label>
  </div>

  <p class="text-[11px] leading-relaxed text-neutral-500">
    Leave a box empty and the section inherits whatever the library CSS already sets.
    Give the band a background and the content a width, and you get the usual layout:
    colour or image across the whole window, words down the middle.
  </p>
</div>
