{{-- Settings → Design. One root element: it sits inside an x-if template. --}}
<form class="settings-stack" @submit.prevent="saveGroup('design')" autocomplete="off" x-init="loadDesignFonts()">

  <div class="design-layout">
    <div class="settings-stack min-w-0 flex-1">

      {{-- colours --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Colours</h3>
          <p>The brand colour runs through buttons, links, highlights and hovers; the lighter and darker shades are worked out from it.</p>
        </div>
        <div class="flex flex-col gap-5 p-5">
          <template x-for="c in [
            { key: 'primary', label: 'Brand colour', hint: 'Buttons, links, the accents.' },
            { key: 'text', label: 'Text colour', hint: 'Headings and body text.' },
            { key: 'background', label: 'Page background', hint: 'Behind the page content.' },
          ]" :key="c.key">
            <div class="design-color">
              <div class="min-w-0">
                <div class="text-[13px] font-semibold" x-text="c.label"></div>
                <div class="text-[12px] text-neutral-500" x-text="c.hint"></div>
              </div>
              <div class="flex flex-wrap items-center gap-1.5">
                <template x-for="swatch in designSwatches[c.key]" :key="swatch">
                  <button type="button" class="design-swatch" :style="`background:${swatch}`" :title="swatch"
                          :class="design[c.key]?.toLowerCase() === swatch && 'is-on'" @click="design[c.key] = swatch" :disabled="!can.update"></button>
                </template>
                <input type="color" class="design-picker" x-model="design[c.key]" :aria-label="c.label" :disabled="!can.update">
                <input class="input !h-[32px] w-[96px] font-mono !text-[12px]" x-model.lazy="design[c.key]" maxlength="7" :disabled="!can.update" :aria-label="`${c.label} (hex)`">
              </div>
              <p class="field-error sm:col-span-2" x-show="fieldError(c.key)" x-text="fieldError(c.key)"></p>
            </div>
          </template>
          <p class="design-contrast" x-show="contrastWarning" x-text="contrastWarning"></p>
        </div>
      </section>

      {{-- fonts --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Fonts</h3>
          <p>From Google Fonts. Pick a Bengali font if the site is written in বাংলা — other fonts have no Bengali letters.</p>
        </div>
        <div class="settings-grid p-5">
          <template x-for="f in [{ key: 'heading_font', label: 'Headings', sample: 'The quick brown fox — শিরোনাম' }, { key: 'body_font', label: 'Body text', sample: 'Pack my box with five dozen liquor jugs. এটি একটি নমুনা লেখা।' }]" :key="f.key">
            <div class="field">
              <label x-text="f.label"></label>
              <select class="input" x-model="design[f.key]" @change="loadDesignFonts()" :disabled="!can.update">
                <template x-for="group in fontGroups" :key="group.label">
                  <optgroup :label="group.label">
                    <template x-for="font in group.fonts" :key="font.value">
                      <option :value="font.value" x-text="font.label" :selected="font.value === design[f.key]"></option>
                    </template>
                  </optgroup>
                </template>
              </select>
              <p class="design-font-sample" :style="`font-family:${fontStack(design[f.key])};font-weight:${f.key === 'heading_font' ? 800 : 400}`" x-text="f.sample"></p>
            </div>
          </template>

          <div class="field">
            <label class="flex justify-between">Reading text size <span class="font-normal text-neutral-500" x-text="`${design.text_size}px`"></span></label>
            <input type="range" :min="limits.text_size[0]" :max="limits.text_size[1]" x-model.number="design.text_size" class="design-range" :disabled="!can.update">
            <p class="mt-1 text-[11px] text-neutral-500">The text of posts. 16–18px reads comfortably.</p>
          </div>
          <div class="field">
            <label class="flex justify-between">Corner rounding <span class="font-normal text-neutral-500" x-text="design.radius ? `${design.radius}px` : 'Square'"></span></label>
            <input type="range" :min="limits.radius[0]" :max="limits.radius[1]" x-model.number="design.radius" class="design-range" :disabled="!can.update">
            <p class="mt-1 text-[11px] text-neutral-500">Buttons, fields, cards and chips on the site.</p>
          </div>
        </div>
      </section>

      {{-- widths --}}
      <section class="panel">
        <div class="settings-panel-head">
          <h3>Widths</h3>
          <p>How wide the site runs on a big screen, and how wide a single post's text is. Phones always use the full screen.</p>
        </div>
        <div class="settings-grid p-5">
          <div class="field">
            <label class="flex justify-between">Site width <span class="font-normal text-neutral-500" x-text="`${design.site_width}px`"></span></label>
            <input type="range" :min="limits.site_width[0]" :max="limits.site_width[1]" step="10" x-model.number="design.site_width" class="design-range" :disabled="!can.update">
            <p class="mt-1 text-[11px] text-neutral-500">The blog, page sections, header and footer. Most sites use 1100–1280px.</p>
          </div>
          <div class="field">
            <label class="flex justify-between">Single post width <span class="font-normal text-neutral-500" x-text="`${design.content_width}px`"></span></label>
            <input type="range" :min="limits.content_width[0]" :max="limits.content_width[1]" step="10" x-model.number="design.content_width" class="design-range" :disabled="!can.update">
            <p class="mt-1 text-[11px] text-neutral-500">The column a post is read in. 680–760px keeps lines easy to follow.</p>
          </div>
          <div class="settings-grid-full design-widths" aria-hidden="true">
            <div class="design-widths-site" :style="`width:${design.site_width / 16}%`"><span x-text="`Site ${design.site_width}px`"></span></div>
            <div class="design-widths-post" :style="`width:${design.content_width / 16}%`"><span x-text="`Post ${design.content_width}px`"></span></div>
            <div class="design-widths-screen">A 1600px screen</div>
          </div>
        </div>
      </section>
    </div>

    {{-- a sample of the site, drawn with the choices above --}}
    <aside class="design-sample-wrap">
      <div class="design-sample-head">Sample</div>
      <div class="design-sample" :style="designSampleStyle">
        <div class="design-sample-kicker">Blog</div>
        <h2 class="design-sample-title">Designing a calmer homepage</h2>
        <p class="design-sample-text">Good design is mostly subtraction. Here is how we cut the page in half — and <a href="#" @click.prevent>why it worked</a>.</p>
        <div class="flex flex-wrap gap-2">
          <span class="design-sample-btn">Read the post</span>
          <span class="design-sample-btn is-outline">Subscribe</span>
        </div>
        <div class="design-sample-card">
          <span class="design-sample-chip">Design</span>
          <strong>Five small layout rules</strong>
          <span class="design-sample-muted">4 min read</span>
        </div>
      </div>
      <p class="p-3 text-[11px] leading-relaxed text-neutral-500">
        Saving applies it to every page, section, header and footer. The admin panel keeps its own look.
      </p>
    </aside>
  </div>

  <div class="settings-savebar">
    <button type="button" class="btn btn-ghost" @click="resetDesign()" :disabled="!can.update">Back to the defaults</button>
    <span class="counter ml-auto" x-show="isDirty('design')">Unsaved changes</span>
    <button type="submit" class="btn btn-primary" :disabled="saving || !can.update || !isDirty('design')"
            x-text="saving ? 'Saving…' : 'Save design'"></button>
  </div>
</form>
