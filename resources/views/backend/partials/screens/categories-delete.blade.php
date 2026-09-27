{{-- Deleting a category says out loud what happens to everything attached to
     it, and lets you decide the part that cannot be guessed: where its posts
     go. Sub-categories always move up a level rather than being destroyed. --}}
<template x-if="deleting">
  <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
    <div class="modal w-[min(520px,100%)]">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        <span>Delete category</span>
      </div>

      <div class="modal-body">
        <p class="text-sm leading-relaxed text-neutral-700">
          Delete <strong class="text-ink" x-text="deleting.name"></strong>?
          This cannot be undone.
        </p>

        <ul class="delete-impact">
          <li x-show="deleteImpact.posts > 0">
            <strong x-text="deleteImpact.posts"></strong>
            <span x-text="deleteImpact.posts === 1 ? 'post is filed here' : 'posts are filed here'"></span>
          </li>
          <li x-show="deleteImpact.promoted > 0">
            <strong x-text="deleteImpact.promoted"></strong>
            <span x-text="(deleteImpact.promoted === 1 ? 'sub-category' : 'sub-categories') + ' will move up a level'"></span>
          </li>
          <li x-show="deleteImpact.posts === 0 && deleteImpact.promoted === 0">
            Nothing is filed here and it has no sub-categories.
          </li>
        </ul>

        <div class="field" x-show="deleteImpact.posts > 0">
          <label>Move those posts to</label>
          <select class="input" x-model="movePostsTo">
            <option value="">No category — leave them uncategorised</option>
            <template x-for="option in moveChoices" :key="option.id">
              <option :value="option.id" x-text="indentLabel(option)"></option>
            </template>
          </select>
          <p class="mt-1 text-[11px] text-neutral-500">
            The posts themselves are never deleted.
          </p>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
        <button class="btn btn-primary" @click="confirmDelete()">Delete category</button>
      </div>
    </div>
  </div>
</template>
