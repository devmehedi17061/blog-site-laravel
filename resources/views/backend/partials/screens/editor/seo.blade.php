{{-- The SEO tab, shared by the post and page editors (see lib/seo-panel.js). --}}
<div class="flex flex-col gap-6">

  <section class="panel">
    <button type="button" class="seo-panel-head" @click="seoOpen = !seoOpen" :aria-expanded="seoOpen">
      <span>
        <span class="seo-panel-title">SEO</span>
        <span class="seo-panel-hint">Leave blank to generate from the title and excerpt automatically.</span>
      </span>
      <svg class="seo-panel-chevron" :class="!seoOpen && 'is-closed'" width="16" height="16" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m18 15-6-6-6 6"></path>
      </svg>
    </button>

    <div class="seo-grid" x-show="seoOpen">
      {{-- SEO title --}}
      <div class="field">
        <label for="seo-title" class="flex items-center gap-2">
          SEO title
          <span class="counter ml-auto" :class="(draft.meta_title || draft.title || '').length > 60 && 'counter-over'"
                x-text="`${(draft.meta_title || draft.title || '').length} / 60`"></span>
        </label>
        <input id="seo-title" class="input" :class="fieldError('meta_title') && 'input-invalid'"
               x-model="draft.meta_title" :placeholder="draft.title || 'Falls back to the title'">
        <p class="seo-hint">Around 60 characters shows in full on Google.</p>
        <p class="field-error" x-show="fieldError('meta_title')" x-text="fieldError('meta_title')"></p>
      </div>

      {{-- meta description --}}
      <div class="field">
        <label for="seo-description" class="flex items-center gap-2">
          Meta description
          <span class="counter ml-auto" :class="(draft.meta_description || draft.excerpt || '').length > 155 && 'counter-over'"
                x-text="`${(draft.meta_description || draft.excerpt || '').length} / 155`"></span>
        </label>
        <textarea id="seo-description" class="input !min-h-[62px]" :class="fieldError('meta_description') && 'input-invalid'"
                  x-model="draft.meta_description" :placeholder="draft.excerpt || 'Falls back to the excerpt'"></textarea>
        <p class="seo-hint">Around 155 characters shows in full on Google.</p>
        <p class="field-error" x-show="fieldError('meta_description')" x-text="fieldError('meta_description')"></p>
      </div>

      {{-- focus keyword --}}
      <div class="field">
        <label for="seo-keyword">Focus keyword</label>
        <input id="seo-keyword" class="input" :class="fieldError('focus_keyword') && 'input-invalid'"
               x-model="draft.focus_keyword" placeholder="The phrase this should rank for">
        <p class="seo-hint">The checks below look for it in the title, address, description and opening text.</p>
        <p class="field-error" x-show="fieldError('focus_keyword')" x-text="fieldError('focus_keyword')"></p>
      </div>

      {{-- canonical --}}
      <div class="field">
        <label for="seo-canonical">Canonical URL</label>
        <input id="seo-canonical" class="input font-mono !text-[13px]" :class="fieldError('canonical_url') && 'input-invalid'"
               x-model="draft.canonical_url" placeholder="https://…">
        <p class="seo-hint">Only set this when the content duplicates another URL.</p>
        <p class="field-error" x-show="fieldError('canonical_url')" x-text="fieldError('canonical_url')"></p>
      </div>

      {{-- robots --}}
      <div class="field">
        <label for="seo-robots">Robots</label>
        <select id="seo-robots" class="input" :class="fieldError('robots') && 'input-invalid'" x-model="draft.robots">
          <template x-for="option in (options.robots ?? [])" :key="option.value">
            <option :value="option.value" x-text="option.label" :selected="option.value === draft.robots"></option>
          </template>
        </select>
        <p class="seo-hint" x-show="draft.robots?.startsWith('noindex')">Search engines will leave this out of results. It stays reachable by link.</p>
        <p class="field-error" x-show="fieldError('robots')" x-text="fieldError('robots')"></p>
      </div>

      {{-- social title --}}
      <div class="field">
        <label for="seo-og-title">Social title</label>
        <input id="seo-og-title" class="input" :class="fieldError('og_title') && 'input-invalid'"
               x-model="draft.og_title" :placeholder="draft.meta_title || draft.title || 'Falls back to the SEO title'">
        <p class="seo-hint">Shown when shared on Facebook, X, LinkedIn or WhatsApp.</p>
        <p class="field-error" x-show="fieldError('og_title')" x-text="fieldError('og_title')"></p>
      </div>

      {{-- social description --}}
      <div class="field">
        <label for="seo-og-description">Social description</label>
        <textarea id="seo-og-description" class="input !min-h-[62px]" :class="fieldError('og_description') && 'input-invalid'"
                  x-model="draft.og_description" :placeholder="draft.meta_description || draft.excerpt || 'Falls back to the meta description'"></textarea>
        <p class="field-error" x-show="fieldError('og_description')" x-text="fieldError('og_description')"></p>
      </div>

      {{-- social image --}}
      <div class="field">
        <label for="seo-og-image">Social image URL</label>
        <div class="flex">
          <input id="seo-og-image" class="input min-w-0 flex-1 font-mono !text-[12px]" :class="fieldError('og_image') && 'input-invalid'"
                 x-model.trim="draft.og_image" :placeholder="draft.featured_image?.url ? 'Falls back to the featured image' : '/storage/media/… or https://…'">
          <button type="button" class="seo-image-button" title="Choose from the media library" @click="chooseSocialImage()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="3" width="18" height="18"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path d="m21 15-5-5L5 21"></path>
            </svg>
          </button>
        </div>
        <p class="seo-hint">Use the image button to pick from the media library, or paste a full URL. 1200 × 630 works everywhere.</p>
        <p class="field-error" x-show="fieldError('og_image')" x-text="fieldError('og_image')"></p>
      </div>
    </div>
  </section>

  {{-- how it will look --}}
  <div class="grid gap-6 xl:grid-cols-2">
    <section>
      <h4 class="mb-3 flex items-center gap-2.5"><span class="h-3 w-3 bg-accent"></span> Google result</h4>
      <div class="serp">
        <div class="serp-url" x-text="previewUrl"></div>
        <div class="serp-title" x-text="previewTitle"></div>
        <div class="serp-desc" x-text="previewDescription"></div>
      </div>
      <p class="seo-hint mt-2" x-show="draft.robots?.startsWith('noindex')">Set to noindex — Google will not show this at all.</p>
    </section>

    <section>
      <h4 class="mb-3 flex items-center gap-2.5"><span class="h-3 w-3 bg-accent"></span> When shared</h4>
      <div class="social-card">
        <div class="social-card-image">
          <img x-show="socialImage" :src="socialImage" alt="">
          <span x-show="!socialImage">No image — add a social or featured image</span>
        </div>
        <div class="social-card-text">
          <span class="social-card-domain" x-text="socialDomain"></span>
          <span class="social-card-title" x-text="socialTitle"></span>
          <span class="social-card-desc" x-text="socialDescription"></span>
        </div>
      </div>
    </section>
  </div>

  {{-- checklist --}}
  <section class="panel">
    <div class="panel-head">
      <span class="h-2.5 w-2.5 bg-accent"></span> SEO checks
      <span class="ml-auto" x-text="`${seo.score} / 100`"></span>
    </div>

    <div class="p-5">
      <div class="score-bar mb-5">
        <div class="score-fill"
             :style="`width:${seo.score}%; background:${seo.verdict === 'good' ? '#1c6b3f' : seo.verdict === 'warn' ? '#d99100' : 'var(--color-accent)'}`"></div>
      </div>

      <template x-for="check in seo.checks" :key="check.key">
        <div class="seo-check" :data-check="check.key" :data-status="check.status">
          <span class="seo-dot" :class="`seo-${check.status}`"></span>
          <span x-text="check.label"></span>
          <span class="seo-detail" x-text="check.detail"></span>
        </div>
      </template>
    </div>
  </section>
</div>
