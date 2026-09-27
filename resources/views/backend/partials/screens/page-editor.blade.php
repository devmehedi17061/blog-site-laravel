<template x-if="$store.ui.screen === 'pages' && $store.ui.editing">
<div x-data="pageEditor" x-init="init()">

  <div class="editor-bar">
    <button type="button" class="btn btn-ghost btn-sm" @click="backToList()">← Pages</button>

    <span class="font-heading text-[12px] font-bold tracking-[0.08em] text-neutral-600 uppercase"
          x-text="isNew ? 'New page' : 'Edit page'"></span>

    <span class="tag ml-1"
          :class="draft.status === 'Published' ? 'tag-accent' : draft.status === 'Scheduled' ? 'tag-accent-2' : 'tag-neutral'"
          x-text="draft.status"></span>
    <span class="tag tag-accent gap-1" x-show="draft.is_home"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"></path></svg> Homepage</span>

    <div class="ml-auto flex items-center gap-2">
      <span class="counter" x-show="saving">Saving…</span>
      <button type="button" class="btn btn-secondary btn-sm" x-show="draft.id" @click="openHistory()" title="Saved versions of this page">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l3 2"></path></svg>
        History
      </button>
      <button type="button" class="btn btn-secondary btn-sm" @click="$store.ui.go('sections')">
        Section library ↗
      </button>
      <a class="btn btn-secondary btn-sm" x-show="draft.id" target="_blank" rel="noopener"
         :href="draft.is_live ? draft.public_url : draft.preview_url"
         x-text="draft.is_live ? 'View ↗' : 'Preview ↗'"></a>
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
      <div class="min-w-0 flex-1">

        <div class="tabs mb-5">
          <button type="button" class="tab" :class="tab === 'content' && 'tab-active'" @click="tab = 'content'">Content</button>
          <button type="button" class="tab" :class="tab === 'sections' && 'tab-active'" @click="tab = 'sections'">
            Sections
            <span class="ml-1.5 text-[10px] tabular-nums" x-text="`(${sections.length})`"></span>
          </button>
          <button type="button" class="tab" :class="tab === 'seo' && 'tab-active'" @click="tab = 'seo'">
            SEO
            <span class="ml-1.5 inline-block h-2 w-2 align-middle" :class="`seo-${seo.verdict}`"></span>
          </button>
          <button type="button" class="tab" :class="tab === 'code' && 'tab-active'" @click="tab = 'code'">
            Code
            <span class="ml-1.5 inline-block h-2 w-2 align-middle bg-accent" x-show="(draft.custom_css || '').trim() || (draft.custom_js || '').trim()" title="Has its own code"></span>
          </button>
        </div>

        {{-- content --}}
        <div x-show="tab === 'content'" class="flex flex-col gap-5">
          <div class="panel p-5">
            <input class="title-input" x-model="draft.title" placeholder="Page title">
            <p class="field-error" x-show="fieldError('title')" x-text="fieldError('title')"></p>

            <div class="mt-3 flex items-center gap-2 border-t border-line pt-3">
              <span class="text-[11px] tracking-[0.08em] text-neutral-500 uppercase">Slug</span>
              <input class="slug-input" x-model="draft.slug" :placeholder="slugPreview">
            </div>
            <p class="field-error" x-show="fieldError('slug')" x-text="fieldError('slug')"></p>
            <p class="mt-1.5 text-[11px] text-neutral-500" x-show="draft.is_home">
              This is the homepage: visitors see it at <span class="font-mono">/</span>, and this address redirects there.
            </p>
          </div>

          <div class="panel p-5">
            <div class="field">
              <label>Excerpt <span class="font-normal text-neutral-500">— used in listings and, if the meta description is blank, by Google</span></label>
              <textarea class="input !min-h-[80px]" x-model="draft.excerpt"></textarea>
              <p class="field-error" x-show="fieldError('excerpt')" x-text="fieldError('excerpt')"></p>
            </div>
          </div>

          <div class="panel p-5" x-show="isNew">
            <div class="field">
              <label>Template</label>
              <select class="input" x-model="draft.template" @change="applyTemplateLayout()">
                <template x-for="template in options.templates" :key="template.value">
                  <option :value="template.value" x-text="template.label"></option>
                </template>
              </select>
              <p class="mt-1.5 text-[11px] text-neutral-500" x-text="templateSummary"></p>
            </div>
          </div>
        </div>

        {{-- sections --}}
        <div x-show="tab === 'sections'" class="flex flex-col gap-4">
          <template x-if="isNew">
            <div class="panel table-empty">
              Save the page first — its sections are created from the
              <strong x-text="draft.template"></strong> template.
            </div>
          </template>

          <template x-if="!isNew">
            <div class="flex flex-col gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[13px] text-neutral-600">
                  Add a section from the library and fill in its fields, or write a blank one.
                  Changes here save with the page, or on their own.
                </span>
                <button type="button" class="btn btn-primary btn-sm ml-auto"
                        @click="saveSectionsNow()" :disabled="sectionsSaving || !dirty"
                        x-text="sectionsSaving ? 'Saving…' : 'Save sections'"></button>
              </div>

              @include('backend.partials.screens.sections.panel')
            </div>
          </template>
        </div>

        {{-- seo --}}
        <div x-show="tab === 'code'">
          @include('backend.partials.screens.editor.code', ['noun' => 'page'])
        </div>

        <div x-show="tab === 'seo'">
          @include('backend.partials.screens.editor.seo')
        </div>
      </div>

      @include('backend.partials.screens.page-editor-sidebar')
    </div>
  </div>

  @include('backend.partials.screens.editor.history')
</div>
</template>
