<aside class="flex w-full flex-col gap-5 lg:w-[300px] lg:flex-none">

  {{-- publish --}}
  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Publish</div>
    <div class="flex flex-col gap-4 p-4">

      <div class="field">
        <label>Status</label>
        <select class="input" x-model="draft.status">
          <template x-for="status in options.statuses" :key="status">
            <option :value="status" x-text="status"></option>
          </template>
        </select>
        <p class="field-error" x-show="fieldError('status')" x-text="fieldError('status')"></p>
      </div>

      <div class="field" x-show="draft.status !== 'Draft'">
        <label>Publish date</label>
        <input type="date" class="input" x-model="draft.published_at">
        <p class="mt-1.5 text-[11px] text-neutral-500">
          Leave blank to stamp it now. A future date keeps the post hidden until then.
        </p>
        <p class="field-error" x-show="fieldError('published_at')" x-text="fieldError('published_at')"></p>
      </div>

      <div class="flex gap-2 border-t border-line pt-4">
        <button type="button" class="btn btn-secondary flex-1" @click="save()" :disabled="saving">
          <span x-text="saving ? 'Saving…' : 'Save'"></span>
        </button>
        <button type="button" class="btn btn-primary flex-1" @click="save('Published')" :disabled="saving"
                x-show="draft.status !== 'Published'">
          Publish
        </button>
        <a class="btn btn-secondary flex-1" x-show="draft.status === 'Published' && draft.id"
           :href="draft.public_url" target="_blank" rel="noopener">View ↗</a>
      </div>
    </div>
  </section>

  {{-- featured image --}}
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
                @click="chooseFeaturedImage()" :disabled="uploading">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
            <rect x="3" y="3" width="18" height="18"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <path d="m21 15-5-5L5 21"></path>
          </svg>
          <span x-text="uploading ? 'Uploading…' : 'Choose from library'"></span>
        </button>
      </template>

      <button type="button" class="btn btn-secondary btn-sm" x-show="!draft.featured_image"
              @click="$refs.featuredInput.click()" :disabled="uploading">
        Or upload a new file
      </button>

      <input type="file" accept="image/*" class="hidden" x-ref="featuredInput" @change="setFeaturedImage($event)">
      <p class="field-error" x-show="fieldError('media_id')" x-text="fieldError('media_id')"></p>
      <p class="text-[11px] leading-relaxed text-neutral-500">
        Shown on the blog card, at the top of the post, and as the social share image.
      </p>
    </div>
  </section>

  {{-- organise --}}
  <section class="panel">
    <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Organise</div>
    <div class="flex flex-col gap-4 p-4">

      <div class="field">
        <label>Category</label>
        <select class="input" x-model="draft.category_id">
          <option value="">No category</option>
          {{-- Indented with figure dashes, which keeps the shape readable
               in a native select where markup is not allowed. --}}
          <template x-for="category in options.categories" :key="category.id">
            <option :value="category.id"
                    x-text="' '.repeat((category.depth ?? 0) * 3) + (category.depth ? '– ' : '') + category.name"
                    :title="category.path ?? category.name"></option>
          </template>
        </select>
        <p class="field-error" x-show="fieldError('category_id')" x-text="fieldError('category_id')"></p>
      </div>

      <div class="field">
        <label>Author</label>
        <select class="input" x-model="draft.user_id">
          <option value="">Assign to me</option>
          <template x-for="author in options.authors" :key="author.id">
            <option :value="author.id" x-text="author.name"></option>
          </template>
        </select>
        <p class="field-error" x-show="fieldError('user_id')" x-text="fieldError('user_id')"></p>
      </div>
    </div>
  </section>
</aside>
