<template x-if="confirming">
  <div class="modal-backdrop" @click.self="cancelDelete()" @keydown.escape.window="cancelDelete()">
    <div class="modal w-[min(440px,100%)]">
      <div class="modal-head">
        <span class="h-2.5 w-2.5 bg-accent"></span>
        Move to trash
      </div>
      <div class="modal-body">
        <p class="text-sm leading-relaxed text-neutral-700">
          Move <strong class="text-ink" x-text="confirming.title"></strong> to the trash? It leaves the site now,
          and can be restored from <strong class="text-ink">Trash</strong> for 30 days.
        </p>
      </div>
      <div class="modal-foot">
        <button class="btn btn-secondary" @click="cancelDelete()">Cancel</button>
        <button class="btn btn-primary" @click="confirmDelete()">Move to trash</button>
      </div>
    </div>
  </div>
</template>
