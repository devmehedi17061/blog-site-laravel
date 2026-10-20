{{-- The page's Sections tab: which sections this page shows, in what order,
     and what each one says. --}}
<div class="builder">

  <div class="builder-bar">
    <button type="button" class="add-choice" @click="openLibrary()">
      <span class="add-choice-mark">+</span>
      <span>
        <span class="add-choice-title">Add from library</span>
        <span class="add-choice-hint">A section someone built once — fill in its fields</span>
      </span>
    </button>

    <button type="button" class="add-choice" @click="addBlankSection()">
      <span class="add-choice-mark">&lt;/&gt;</span>
      <span>
        <span class="add-choice-title">Add blank section</span>
        <span class="add-choice-hint">Write HTML and CSS for this page only</span>
      </span>
    </button>

    <span class="counter ml-auto" x-show="dirty">Unsaved changes</span>
  </div>

  <template x-if="sectionsError">
    <div class="alert m-3" x-text="sectionsError"></div>
  </template>

  <div class="builder-grid">
    @include('backend.partials.screens.sections.list')

    <div class="builder-col builder-col-edit">
      <template x-if="!activeSection">
        <div class="p-5 text-[13px] text-neutral-600">
          Add a section above, or pick one on the left to edit it.
        </div>
      </template>

      <template x-if="activeSection">
        <div class="flex min-h-0 flex-1 flex-col">

          <div class="section-settings">
            <input class="input !h-[30px] flex-[1_1_160px] !text-[13px]"
                   x-model="activeSection.name" @input="touch()" placeholder="Section name">

            <span class="tag" :class="isLibrarySection ? 'tag-accent' : 'tag-neutral'"
                  x-text="sectionMeta(activeSection)"></span>

            <button type="button" class="btn btn-ghost btn-sm !px-2" x-show="isLibrarySection && activeSection.template"
                    @click="$store.ui.go('sections', activeSection.template.id)">Edit in library ↗</button>
          </div>

          <p class="field-error px-3" x-show="sectionError(activeIndex, 'name')"
             x-text="sectionError(activeIndex, 'name')"></p>

          <div class="pane-tabs">
            <button type="button" class="pane-tab" :class="pane === 'content' && 'pane-tab-active'"
                    x-show="isLibrarySection" @click="pane = 'content'">Content</button>
            <button type="button" class="pane-tab" :class="pane === 'html' && 'pane-tab-active'"
                    x-show="!isLibrarySection" @click="pane = 'html'">HTML</button>
            <button type="button" class="pane-tab" :class="pane === 'css' && 'pane-tab-active'"
                    x-show="!isLibrarySection" @click="pane = 'css'">CSS</button>
            <button type="button" class="pane-tab" :class="pane === 'style' && 'pane-tab-active'"
                    @click="pane = 'style'">Section style</button>
          </div>

          @include('backend.partials.screens.sections.values')
          @include('backend.partials.screens.sections.blank')
          @include('backend.partials.screens.sections.style')
        </div>
      </template>
    </div>

    <div class="builder-col builder-col-preview">
      <div class="builder-col-head">Live preview</div>

      <div class="min-h-0 flex-1">
        <template x-if="activeSection">
          <div class="h-full min-h-[420px] w-full" x-live-frame="previewDoc" data-title="Section preview"></div>
        </template>

        <div x-show="!activeSection" class="p-4 text-[13px] text-neutral-600">No section selected.</div>
      </div>
    </div>
  </div>
</div>

@include('backend.partials.screens.sections.picker')
