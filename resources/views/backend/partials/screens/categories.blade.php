<template x-if="$store.ui.screen === 'categories'">
<div x-data="categories" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">Content</div>
      <h2>Categories</h2>
      <div class="page-subtitle">
        Up to three levels. A category page lists its own posts and everything filed beneath it.
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <span class="counter" x-show="orderDirty">Order not saved</span>
      <button type="button" class="btn btn-ghost btn-sm" x-show="orderDirty" @click="discardOrder()">Undo</button>
      <button type="button" class="btn btn-secondary" x-show="orderDirty" @click="saveOrder()"
              :disabled="savingOrder" x-text="savingOrder ? 'Saving…' : 'Save order'"></button>
      <button type="button" class="btn btn-primary" x-show="$store.auth.can('categories.create')"
              @click="startNew()">+ New category</button>
    </div>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading categories…</div>

  <div x-show="!loading" class="menu-builder">

    {{-- the tree --}}
    <div class="menu-tree !flex-[2.2_1_400px]">
      <div class="builder-col-head">
        <span>All categories</span>
        <span class="ml-auto font-normal opacity-70"
              x-text="`${topLevelCount} top level · ${items.length} total`"></span>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <template x-for="(row, index) in items" :key="row.id">
          <div class="menu-row"
               :class="[isSelected(row) && 'is-active', isDropTarget(index) && 'is-drop']"
               :style="`--menu-depth:${row.depth}`"
               draggable="true"
               @dragstart="onDragStart(index, $event)"
               @dragover="onDragOver(index, $event)"
               @drop="onDrop(index, $event)"
               @dragend="onDragEnd()">

            <span class="menu-row-grip" title="Drag to move">⠿</span>

            <button type="button" class="menu-row-main" @click="select(row)">
              <span class="menu-row-label">
                <img class="category-row-thumb" x-show="row.image" :src="row.image?.url" alt="">
                <span class="truncate" x-text="row.name"></span>
                <span class="menu-row-kids" x-show="branchLength(index) > 0"
                      x-text="branchLength(index)" title="Sub-categories"></span>
              </span>
              <span class="menu-row-meta" x-text="meta(row)"></span>
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
              <button type="button" title="Add a sub-category" x-show="$store.auth.can('categories.create')"
                      @click.stop="startNew(row.id)" :disabled="row.depth >= maxDepth">+</button>
              <button type="button" title="Delete" x-show="row.can.delete"
                      @click.stop="askDelete(row)">✕</button>
            </div>
          </div>
        </template>

        <div x-show="isEmpty" class="p-5 text-[13px] text-neutral-600">
          No categories yet. Create the first one.
        </div>
      </div>

      <p class="menu-locations-note !mt-0 !border-t">
        Drag a row to move it, then use <strong>→</strong> to nest it under the row above.
        A category moves with everything inside it.
      </p>
    </div>

    {{-- the one being edited --}}
    <div class="menu-editor !flex-[1.8_1_320px]">
      <div class="builder-col-head">
        <span x-text="creating ? 'New category' : (selected ? 'Edit category' : 'Category')"></span>
        <a class="ml-auto font-normal opacity-70 hover:opacity-100" x-show="selected"
           :href="selected?.public_url" target="_blank" rel="noopener">View ↗</a>
      </div>

      <template x-if="!editing">
        <div class="p-5 text-[13px] text-neutral-600">
          Pick a category to edit it, or create a new one.
        </div>
      </template>

      <template x-if="editing">
        <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3">

          <div class="field">
            <label>Name</label>
            <input class="input" x-model="form.name" @keydown.enter="save()" placeholder="Engineering">
            <p class="field-error" x-show="fieldError('name')" x-text="fieldError('name')"></p>
          </div>

          <div class="field">
            <label>Address <span class="font-normal text-neutral-500">— built from the name if left blank</span></label>
            <div class="flex items-center gap-2">
              <span class="font-mono text-[13px] text-neutral-600">/category/</span>
              <input class="slug-input flex-1" x-model="form.slug" placeholder="engineering">
            </div>
            <p class="field-error" x-show="fieldError('slug')" x-text="fieldError('slug')"></p>
          </div>

          <div class="field">
            <label>Sits under</label>
            <select class="input" x-model="form.parent_id">
              <option :value="null">Nothing — a top-level category</option>
              <template x-for="option in parentChoices" :key="option.id">
                <option :value="option.id" x-text="indentLabel(option)"></option>
              </template>
            </select>
            <p class="mt-1 text-[11px] text-neutral-500">
              A category cannot sit inside itself, or below the third level.
            </p>
          </div>

          <div class="field">
            <label>Description <span class="font-normal text-neutral-500">— shown on the category page</span></label>
            <textarea class="input !min-h-[70px]" x-model="form.description"></textarea>
          </div>

          <div class="field">
            <label>Image</label>
            <div class="media-value">
              <img class="media-value-thumb" x-show="form.image" :src="form.image?.url" alt="">
              <span class="min-w-0 flex-1 truncate font-mono text-[12px] text-neutral-600"
                    x-text="form.image ? form.image.url : 'No image'"></span>
              <button type="button" class="btn btn-secondary btn-sm" @click="chooseImage()">Choose</button>
              <button type="button" class="btn btn-ghost btn-sm !px-2" x-show="form.image"
                      @click="clearImage()">Clear</button>
            </div>
          </div>

          <div class="style-group">
            <div class="style-group-head">
              <span class="style-group-title">Search engines</span>
              <span class="style-group-hint">Falls back to the name and description</span>
            </div>

            <div class="style-group-body">
              <div class="field">
                <label>Meta title</label>
                <input class="input" x-model="form.meta_title" :placeholder="form.name">
              </div>

              <div class="field">
                <label>Meta description</label>
                <textarea class="input !min-h-[60px] !text-[13px]" x-model="form.meta_description"
                          :placeholder="form.description"></textarea>
              </div>
            </div>
          </div>

          <div class="menu-editor-depth">
            <button type="button" class="btn btn-ghost btn-sm" x-show="selected" @click="clearSelection()">Close</button>
            <button type="button" class="btn btn-primary btn-sm ml-auto" @click="save()" :disabled="saving"
                    x-text="saving ? 'Saving…' : (form.id ? 'Save category' : 'Create category')"></button>
          </div>
        </div>
      </template>
    </div>
  </div>

  @include('backend.partials.screens.categories-delete')
</div>
</template>
