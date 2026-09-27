<template x-if="$store.ui.screen === 'posts' && $store.ui.editing">
<div x-data="postEditor" x-init="init()" @beforeunload.window="destroy()">

  <div class="editor-bar">
    <button type="button" class="btn btn-ghost btn-sm" @click="backToList()">← Posts</button>

    <span class="font-heading text-[12px] font-bold tracking-[0.08em] text-neutral-600 uppercase"
          x-text="isNew ? 'New post' : 'Edit post'"></span>

    <span class="tag ml-1" :class="draft.status === 'Published' ? 'tag-accent' : draft.status === 'Scheduled' ? 'tag-accent-2' : 'tag-neutral'"
          x-text="draft.status"></span>

    <div class="ml-auto flex items-center gap-2">
      <span class="counter" x-show="saving">Saving…</span>
      <button type="button" class="btn btn-secondary btn-sm" x-show="draft.id" @click="openHistory()" title="Saved versions of this post">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l3 2"></path></svg>
        History
      </button>
      <button type="button" class="btn btn-secondary" @click="save()" :disabled="saving">Save</button>
      <button type="button" class="btn btn-primary" @click="save('Published')" :disabled="saving"
              x-show="draft.status !== 'Published'">Publish</button>
    </div>
  </div>

  <div class="p-6">

    <template x-if="error">
      <div class="alert mb-5" x-text="error"></div>
    </template>

    <div x-show="loading" class="panel table-empty">Loading the editor…</div>

    <div x-show="!loading" class="flex flex-col gap-6 lg:flex-row">

      {{-- main column --}}
      <div class="min-w-0 flex-1">

        <div class="tabs mb-5">
          <button type="button" class="tab" :class="tab === 'content' && 'tab-active'" @click="tab = 'content'">Content</button>
          <button type="button" class="tab" :class="tab === 'seo' && 'tab-active'" @click="tab = 'seo'">
            SEO
            <span class="ml-1.5 inline-block h-2 w-2 align-middle"
                  :class="`seo-${seo.verdict}`"></span>
          </button>
          <button type="button" class="tab" :class="tab === 'code' && 'tab-active'" @click="tab = 'code'">
            Code
            <span class="ml-1.5 inline-block h-2 w-2 align-middle bg-accent" x-show="(draft.custom_css || '').trim() || (draft.custom_js || '').trim()" title="Has its own code"></span>
          </button>
        </div>

        {{-- content tab --}}
        <div x-show="tab === 'content'" class="flex flex-col gap-5">

          <div class="panel p-5">
            <input class="title-input" x-model="draft.title" placeholder="Post title">
            <p class="field-error" x-show="fieldError('title')" x-text="fieldError('title')"></p>

            <div class="mt-3 flex items-center gap-2 border-t border-line pt-3">
              <span class="text-[11px] tracking-[0.08em] text-neutral-500 uppercase">Slug</span>
              <input class="slug-input" x-model="draft.slug" :placeholder="slugPreview">
            </div>
            <p class="field-error" x-show="fieldError('slug')" x-text="fieldError('slug')"></p>
          </div>

          <div class="panel overflow-hidden">
            @include('backend.partials.screens.editor.toolbar')
            @include('backend.partials.screens.editor.image-bar')
            <div class="editor-scroll" :class="`spacing-${draft.content_spacing}`" x-ref="editor"></div>
          </div>

          <div class="panel p-5">
            <div class="field">
              <label class="flex items-center gap-2">
                Excerpt
                <span class="counter ml-auto" x-text="`${(draft.excerpt || '').length} characters`"></span>
              </label>
              <textarea class="input !min-h-[80px]" :class="fieldError('excerpt') && 'input-invalid'"
                        x-model="draft.excerpt"
                        placeholder="One or two lines for the blog listing and, if you leave the meta description blank, for Google."></textarea>
              <p class="field-error" x-show="fieldError('excerpt')" x-text="fieldError('excerpt')"></p>
            </div>
          </div>
        </div>

        {{-- code tab: this post's own CSS and JavaScript --}}
        <div x-show="tab === 'code'">
          @include('backend.partials.screens.editor.code', ['noun' => 'post'])
        </div>

        {{-- seo tab --}}
        <div x-show="tab === 'seo'">
          @include('backend.partials.screens.editor.seo')
        </div>
      </div>

      @include('backend.partials.screens.editor.sidebar')
    </div>
  </div>

  @include('backend.partials.screens.editor.history')
</div>
</template>
