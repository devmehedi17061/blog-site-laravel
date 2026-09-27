{{-- Rendered once for the whole panel; any screen asks through $store.dialog.
     Sits above the media picker, so a question asked from inside it still shows. --}}
<template x-if="$store.dialog.open">
  <div class="modal-backdrop !z-[70]" @click.self="$store.dialog.cancel()"
       @keydown.escape.window.prevent.stop="$store.dialog.cancel()">
    <form class="modal dialog w-[min(440px,100%)]" role="alertdialog" aria-modal="true"
          aria-labelledby="dialog-title" aria-describedby="dialog-message"
          @submit.prevent="$store.dialog.accept()"
          x-init="$nextTick(() => ($refs.answer ?? $refs.accept)?.focus())">

      <div class="modal-head">
        <span class="h-2.5 w-2.5" :class="$store.dialog.danger ? 'bg-accent' : 'bg-white'"></span>
        <span id="dialog-title" x-text="$store.dialog.title"></span>
      </div>

      <div class="modal-body !gap-3">
        <p id="dialog-message" class="dialog-message" x-show="$store.dialog.message" x-text="$store.dialog.message"></p>

        <template x-if="$store.dialog.kind === 'prompt'">
          <div class="field">
            <label for="dialog-answer" x-show="$store.dialog.label" x-text="$store.dialog.label"></label>
            <input id="dialog-answer" class="input" x-ref="answer" x-model="$store.dialog.value"
                   :placeholder="$store.dialog.placeholder" autocomplete="off"
                   @focus="$event.target.select()">
          </div>
        </template>
      </div>

      <div class="modal-foot">
        <button type="button" class="btn btn-secondary" x-show="$store.dialog.kind !== 'alert'"
                @click="$store.dialog.cancel()" x-text="$store.dialog.cancelLabel"></button>
        <button type="submit" class="btn btn-primary" x-ref="accept"
                :class="$store.dialog.danger && 'btn-danger'" x-text="$store.dialog.confirmLabel"></button>
      </div>
    </form>
  </div>
</template>
