<template x-if="$store.ui.screen === 'layout'">
<div x-data="headerFooter" class="p-4 sm:p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Builders</div>
      <h2>Header &amp; footer</h2>
      <div class="page-subtitle">
        Build headers and footers like sections — HTML, CSS and fields — and drop in the logo, menu, search box and more.
        One header and one footer are in use on the site.
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span class="counter" x-show="dirty">Unsaved changes</span>
      <button type="button" class="btn btn-secondary" @click="startNew()" x-show="can.create" x-text="`+ New ${noun}`"></button>
      <button type="button" class="btn btn-secondary" x-show="draft?.id && !draft?.is_active && can.update"
              @click="activate()" :disabled="activating" x-text="activating ? 'Switching…' : 'Use on the site'"></button>
      <button type="button" class="btn btn-primary" @click="save()" :disabled="saving || !draft || !can.update"
              x-text="saving ? 'Saving…' : `Save ${noun}`"></button>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading && !draft" class="panel table-empty">Loading…</div>

  {{-- ── the live preview, full width: headers and footers are wide ───── --}}
  <div class="hfb-stage mb-4" x-show="draft" x-ref="stage">
    <div class="hfb-stage-bar">
      <div class="hfb-seg" role="group" aria-label="Preview size">
        <template x-for="(d, key) in devices" :key="key">
          <button type="button" :class="device === key && 'is-on'" @click="device = key" :aria-pressed="device === key"
                  x-text="d.label"></button>
        </template>
      </div>
      <span class="hfb-stage-note" x-text="`${frameWidth}px${frameScale < 1 ? ' · shown at ' + Math.round(frameScale * 100) + '%' : ''}`"></span>
      <span class="hfb-stage-note ml-auto" x-show="previewing">Updating…</span>
      <span class="hfb-stage-note ml-auto !text-accent-300" x-show="!previewing && previewError" x-text="previewError"></span>
      <span class="hfb-stage-note ml-auto" x-show="!previewing && !previewError">Live preview · unsaved changes included</span>
    </div>
    <div class="hfb-frame-wrap">
      <iframe x-ref="frame" class="hfb-frame" :style="frameStyle" sandbox="allow-scripts" title="Preview"></iframe>
    </div>
  </div>

  <div x-show="!loading || draft" class="builder">
    <div class="builder-grid">

      {{-- ── the library ────────────────────────────────────────────── --}}
      <div class="builder-col builder-col-list">
        <div class="hfb-kinds" role="tablist" aria-label="Show">
          <button type="button" role="tab" :aria-selected="kind === 'header'" :class="kind === 'header' && 'is-on'" @click="setKind('header')">
            Headers <span x-text="count('header')"></span>
          </button>
          <button type="button" role="tab" :aria-selected="kind === 'footer'" :class="kind === 'footer' && 'is-on'" @click="setKind('footer')">
            Footers <span x-text="count('footer')"></span>
          </button>
        </div>

        <div class="flex-none border-b border-line p-2">
          <input class="input !h-[30px] !text-xs" :placeholder="`Search ${noun}s…`" x-model="search">
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
          <template x-for="part in visibleTemplates" :key="part.id">
            <div class="section-row" :class="isActive(part) && 'section-row-active'">
              <button type="button" class="section-row-main" @click="select(part)">
                <span class="flex items-center gap-2">
                  <span class="truncate font-semibold" x-text="part.name"></span>
                  <span class="tag tag-accent !h-[18px] !px-1.5 !text-[10px]" x-show="part.is_active">In use</span>
                </span>
                <span class="block truncate text-[11px] text-neutral-600" x-text="part.description || usageLabel(part)"></span>
              </button>

              <div class="section-row-actions">
                <button type="button" title="Duplicate" @click.stop="duplicate(part)" x-show="can.create">⧉</button>
                <button type="button" title="Delete" @click.stop="remove(part)" x-show="can.delete">✕</button>
              </div>
            </div>
          </template>

          <div x-show="visibleTemplates.length === 0" class="p-4 text-[13px] text-neutral-600">
            <span x-show="search">Nothing matches that search.</span>
            <span x-show="!search" x-text="`No ${noun}s yet. Build the first one.`"></span>
          </div>
        </div>
      </div>

      {{-- ── the editor ─────────────────────────────────────────────── --}}
      <div class="builder-col builder-col-edit hfb-edit">
        <template x-if="!draft">
          <div class="p-5 text-[13px] text-neutral-600">Pick one on the left, or start a new one.</div>
        </template>

        <template x-if="draft">
          <div class="flex min-h-0 flex-1 flex-col">
            <div class="section-settings">
              <input class="input !h-[30px] flex-[1_1_150px] !text-[13px]" x-model="draft.name" @input="touch()" placeholder="Name" aria-label="Name">
              <input class="input !h-[30px] flex-[2_1_200px] !text-[13px]" x-model="draft.description" @input="touch()" placeholder="What is it for?" aria-label="Description">
            </div>
            <p class="field-error px-3" x-show="fieldError('name')" x-text="fieldError('name')"></p>

            <div class="hfb-status">
              <span class="tag" :class="draft.is_active ? 'tag-accent' : 'tag-neutral'"
                    x-text="draft.is_active ? 'In use on the site — saving updates the site' : (draft.id ? 'Not in use' : 'Not saved yet')"></span>
              <label class="check !text-[12px] ml-auto" x-show="draft.kind === 'header'">
                <input type="checkbox" x-model="draft.settings.sticky" @change="touch()"> Stay at the top while scrolling
              </label>
            </div>

            {{-- The arrow beside a menu item with a sub-menu, e.g. "Pricing ▾". --}}
            <div class="hfb-caret" x-show="draft.kind === 'header'" role="radiogroup" aria-label="Drop-down arrow">
              <span class="hfb-caret-label">Drop-down arrow</span>
              @foreach (\App\Models\SitePart::CARETS as $caret)
                <button type="button" role="radio" class="hfb-caret-option"
                        :class="draft.settings.caret === '{{ $caret }}' && 'is-on'" :aria-checked="draft.settings.caret === '{{ $caret }}'"
                        @click="draft.settings.caret = '{{ $caret }}'; touch()" title="{{ ucfirst($caret) }}">
                  <span class="hfb-caret-demo">Menu
                    @if ($caret !== 'none')
                      <span class="hfb-caret-icon">@include('frontend.partials.site-parts.caret', ['style' => $caret])</span>
                    @endif
                  </span>
                  <span class="hfb-caret-name">{{ ucfirst($caret) }}</span>
                </button>
              @endforeach
            </div>

            <div class="pane-tabs">
              <button type="button" class="pane-tab" :class="pane === 'content' && 'pane-tab-active'" @click="pane = 'content'">Content</button>
              <button type="button" class="pane-tab" :class="pane === 'fields' && 'pane-tab-active'" @click="pane = 'fields'">
                Fields <span class="tabular-nums" x-text="`(${fields.length})`"></span>
              </button>
              <button type="button" class="pane-tab" :class="pane === 'html' && 'pane-tab-active'" @click="pane = 'html'">HTML</button>
              <button type="button" class="pane-tab" :class="pane === 'css' && 'pane-tab-active'" @click="pane = 'css'">CSS</button>
            </div>

            @include('backend.partials.screens.header-footer.content')
            @include('backend.partials.screens.library.fields')
            @include('backend.partials.screens.header-footer.code')
          </div>
        </template>
      </div>
    </div>
  </div>
</div>
</template>
