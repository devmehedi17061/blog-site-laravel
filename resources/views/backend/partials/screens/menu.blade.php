<template x-if="$store.ui.screen === 'menu'">
<div x-data="menu" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Menu control</h2>
      <div class="page-subtitle" x-text="footerNote"></div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span class="counter" x-show="dirty">Unsaved changes</span>
      <button type="button" class="btn btn-secondary" @click="addItem()">+ Add item</button>
      <button type="button" class="btn btn-primary" @click="save()" :disabled="saving || !dirty"
              x-text="saving ? 'Saving…' : 'Save menu'"></button>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading the menu…</div>

  <div x-show="!loading" class="menu-builder">

    {{-- which menu --}}
    <div class="menu-locations">
      <div class="builder-col-head">Menus</div>

      <template x-for="name in options.locations" :key="name">
        <button type="button" class="menu-location-row" :class="location === name && 'is-active'"
                @click="switchLocation(name)">
          <span class="font-semibold" x-text="name"></span>
          <span class="menu-location-hint"
                x-text="name === 'Header' ? 'Top of every page' : 'Bottom of every page'"></span>
        </button>
      </template>

      <p class="menu-locations-note">
        Drag a row to move it. Drop it, then use <strong>→</strong> to tuck it under the row above.
        A row moves with everything nested inside it.
      </p>
    </div>

    {{-- the tree --}}
    <div class="menu-tree">
      <div class="builder-col-head">
        <span x-text="`${location} menu`"></span>
        <span class="ml-auto font-normal opacity-70"
              x-text="`${topLevelCount} top level · ${items.length} total`"></span>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <template x-for="(item, index) in items" :key="index">
          <div class="menu-row"
               :class="[isActive(index) && 'is-active', isDropTarget(index) && 'is-drop', errorFor(index) && 'has-error']"
               :style="`--menu-depth:${item.depth}`"
               draggable="true"
               @dragstart="onDragStart(index, $event)"
               @dragover="onDragOver(index, $event)"
               @drop="onDrop(index, $event)"
               @dragend="onDragEnd()">

            <span class="menu-row-grip" title="Drag to move">⠿</span>

            <button type="button" class="menu-row-main" @click="select(index)">
              <span class="menu-row-label">
                <span class="menu-row-icon" x-show="item.icon" x-html="iconSvg(item.icon)"></span>
                <span class="truncate" x-text="item.label"></span>
                <span class="menu-row-kids" x-show="hasChildren(index)"
                      x-text="`${branchLength(index)}`" title="Items nested inside"></span>
              </span>
              <span class="menu-row-meta" x-text="meta(item)"></span>
              <span class="menu-row-error" x-show="errorFor(index)" x-text="errorFor(index)"></span>
            </button>

            <div class="menu-row-actions">
              <button type="button" title="Move out a level" @click.stop="outdent(index)"
                      :disabled="!canOutdent(index)">←</button>
              <button type="button" title="Nest under the row above" @click.stop="indent(index)"
                      :disabled="!canIndent(index)">→</button>
              <button type="button" title="Move up" @click.stop="moveUp(index)"
                      :disabled="index === 0">↑</button>
              <button type="button" title="Move down" @click.stop="moveDown(index)"
                      :disabled="index + branchLength(index) + 1 >= items.length">↓</button>
              <button type="button" title="Duplicate" @click.stop="duplicateItem(index)">⧉</button>
              <button type="button" title="Remove" @click.stop="removeItem(index)">✕</button>
            </div>
          </div>
        </template>

        <div x-show="isEmpty" class="p-5 text-[13px] text-neutral-600">
          This menu is empty. Add the first item.
        </div>
      </div>
    </div>

    {{-- the one being edited --}}
    <div class="menu-editor">
      <div class="builder-col-head">Item</div>

      <template x-if="!active">
        <div class="p-5 text-[13px] text-neutral-600">Pick a row to edit it.</div>
      </template>

      <template x-if="active">
        <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3">

          <div class="field">
            <label>Label</label>
            <input class="input" x-model="active.label" @input="touch()" placeholder="Services">
          </div>

          <div class="field">
            <label>Icon <span class="font-normal text-neutral-500">— shown before the label</span></label>
            <div class="flex flex-wrap items-center gap-2">
              <span class="menu-icon-preview" x-show="active.icon" x-html="iconSvg(active.icon, 18)"></span>
              <span class="menu-icon-preview is-empty" x-show="!active.icon">—</span>

              <button type="button" class="btn btn-secondary btn-sm"
                      @click="openIconPicker(activeIndex)"
                      x-text="active.icon ? 'Change icon' : 'Choose icon'"></button>
              <button type="button" class="btn btn-ghost btn-sm !px-2" x-show="active.icon"
                      @click="clearIcon(activeIndex)">Clear</button>
              <span class="font-mono text-[11px] text-neutral-500" x-text="active.icon"></span>
            </div>
          </div>

          <div class="field">
            <label>Link</label>
            <div class="flex flex-wrap items-center gap-2">
              <input class="input font-mono !text-[13px] min-w-0 flex-1" x-model="active.url"
                     @input="touch()" placeholder="/services or https://…">
              <button type="button" class="btn btn-secondary btn-sm"
                      @click="openLinkPicker(activeIndex)">Pick…</button>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <div class="field flex-[1_1_150px]">
              <label>Opens in</label>
              <select class="input" x-model="active.target" @change="touch()">
                <option value="_self">The same tab</option>
                <option value="_blank">A new tab</option>
              </select>
            </div>

            <div class="field flex-[1_1_150px]">
              <label>Who sees it</label>
              <select class="input" x-model="active.visibility" @change="touch()">
                <template x-for="value in options.visibilities" :key="value">
                  <option :value="value" x-text="value"></option>
                </template>
              </select>
            </div>
          </div>

          <p class="text-[11px] leading-relaxed text-neutral-500">
            <span x-show="active.visibility === 'Public'">Everyone sees this item.</span>
            <span x-show="active.visibility === 'Authenticated'">Only signed-in visitors see this item — it is left out of the page entirely for everyone else.</span>
            <span x-show="active.visibility === 'Admin only'">Only people who can open this panel see it.</span>
          </p>

          <div class="menu-editor-depth">
            <span x-text="`Level ${active.depth + 1} of ${options.max_depth + 1}`"></span>
            <button type="button" class="btn btn-ghost btn-sm !px-2" @click="outdent(activeIndex)"
                    :disabled="!canOutdent(activeIndex)">← Out</button>
            <button type="button" class="btn btn-ghost btn-sm !px-2" @click="indent(activeIndex)"
                    :disabled="!canIndent(activeIndex)">In →</button>
          </div>
        </div>
      </template>
    </div>
  </div>

  @include('backend.partials.screens.menu.icon-picker')
  @include('backend.partials.screens.menu.link-picker')
</div>
</template>
