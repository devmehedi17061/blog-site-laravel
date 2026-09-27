<aside class="flex w-full flex-col gap-5 lg:w-[300px] lg:flex-none">

  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Publish</div>
    <div class="flex flex-col gap-4 p-4">

      <div class="field">
        <label>Status</label>
        <select class="input" x-model="draft.status">
          <template x-for="status in options.statuses" :key="status">
            <option :value="status" x-text="status" :disabled="statusDisabled(status)" :selected="status === draft.status"></option>
          </template>
        </select>
        <p class="field-error" x-show="fieldError('status')" x-text="fieldError('status')"></p>
        <p class="mt-1.5 text-[11px] text-neutral-500" x-show="draft.is_home">
          The homepage has to stay published. Choose another homepage to unpublish this one.
        </p>
      </div>

      <div class="field" x-show="draft.status !== 'Draft'">
        <label>Publish date</label>
        <input type="date" class="input" x-model="draft.published_at">
        <p class="mt-1.5 text-[11px] text-neutral-500">
          Leave blank to stamp it now. A future date keeps the page hidden until then.
        </p>
      </div>

      <div class="flex gap-2 border-t border-line pt-4">
        <button type="button" class="btn btn-secondary flex-1" @click="save()" :disabled="saving">
          <span x-text="saving ? 'Saving…' : 'Save'"></span>
        </button>
        <button type="button" class="btn btn-primary flex-1" @click="save('Published')" :disabled="saving"
                x-show="draft.status !== 'Published'">Publish</button>
        <a class="btn btn-secondary flex-1" x-show="draft.is_live"
           :href="draft.public_url" target="_blank" rel="noopener">View ↗</a>
      </div>

      <a class="btn btn-ghost btn-sm -mt-2 self-start" x-show="draft.id && !draft.is_live"
         :href="draft.preview_url" target="_blank" rel="noopener"
         title="See the saved version before it goes live — only people signed in to the panel can">
        Preview the saved version ↗
      </a>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Layout</div>
    <div class="flex flex-col gap-2 p-4">
      <div class="option-cards" role="radiogroup" aria-label="Layout">
        <template x-for="layout in options.layouts" :key="layout.value">
          <label class="option-card">
            <input type="radio" name="page-layout" :value="layout.value" x-model="draft.layout">
            <span>
              <span class="option-card-label" x-text="layout.label"></span>
              <span class="option-card-summary" x-text="layout.summary"></span>
            </span>
          </label>
        </template>
      </div>
      <p class="field-error" x-show="fieldError('layout')" x-text="fieldError('layout')"></p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Homepage</div>
    <div class="flex flex-col gap-3 p-4 text-[13px] leading-relaxed text-neutral-700">
      <template x-if="draft.is_home">
        <div class="flex flex-col gap-2">
          <p class="m-0"><strong class="text-ink">This page is the homepage.</strong>
            Visitors see it at <span class="font-mono">/</span>, and
            <span class="font-mono" x-text="draft.slug"></span> redirects there.</p>
          <button type="button" class="btn btn-ghost btn-sm self-start" @click="$store.ui.go('pages')">
            Change the homepage in Pages →
          </button>
        </div>
      </template>

      <template x-if="!draft.is_home">
        <div class="flex flex-col gap-2">
          <p class="m-0">Make this the page visitors land on at <span class="font-mono">/</span>.</p>
          <button type="button" class="btn btn-secondary btn-sm self-start" @click="makeHome()"
                  x-show="$store.auth.can('pages.update')"
                  :disabled="!!homeBlocker || homeSaving" x-text="homeSaving ? 'Saving…' : 'Set as homepage'"></button>
          <p class="m-0 text-[11px] text-neutral-500" x-show="homeBlocker" x-text="homeBlocker"></p>
        </div>
      </template>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Featured image</div>
    <div class="flex flex-col gap-3 p-4">

      <template x-if="draft.featured_image">
        <div class="flex flex-col gap-2">
          <img :src="draft.featured_image.url" :alt="draft.featured_image.alt_text ?? draft.featured_image.name"
               class="w-full border border-line-strong object-cover">
          <div class="flex items-center gap-2">
            <span class="truncate text-[12px] text-neutral-600" x-text="draft.featured_image.name"></span>
            <button type="button" class="btn btn-ghost btn-sm ml-auto" @click="chooseFeaturedImage()">Change</button>
            <button type="button" class="btn btn-ghost btn-sm text-accent-700" @click="clearFeaturedImage()">Remove</button>
          </div>
        </div>
      </template>

      <template x-if="!draft.featured_image">
        <button type="button"
                class="flex w-full cursor-pointer flex-col items-center gap-2 border border-dashed border-line-strong bg-muted p-6 text-[13px] text-neutral-600 transition hover:border-accent hover:text-accent-800"
                @click="chooseFeaturedImage()">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
            <rect x="3" y="3" width="18" height="18"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <path d="m21 15-5-5L5 21"></path>
          </svg>
          Choose from library
        </button>
      </template>

      <p class="text-[11px] leading-relaxed text-neutral-500">
        Used as the social share image for this page.
      </p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Author</div>
    <div class="p-4">
      <div class="field">
        <label>Owner</label>
        <select class="input" x-model="draft.user_id">
          <option value="">Assign to me</option>
          <template x-for="author in options.authors" :key="author.id">
            <option :value="author.id" x-text="author.name"></option>
          </template>
        </select>
      </div>
    </div>
  </section>
</aside>
