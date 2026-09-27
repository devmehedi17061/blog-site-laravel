{{-- Everything about the form that is not a field. --}}
<div class="flex flex-col gap-5 lg:flex-row">

  <div class="flex min-w-0 flex-1 flex-col gap-5">
    <div class="panel p-5">
      <div class="field">
        <label>Name</label>
        <input class="input" x-model="draft.name" @input="touch()" placeholder="Contact">
        <p class="field-error" x-show="fieldError('name')" x-text="fieldError('name')"></p>
      </div>

      <div class="field mt-4">
        <label>Address <span class="font-normal text-neutral-500">— where the form posts to</span></label>
        <div class="flex items-center gap-2">
          <span class="font-mono text-[13px] text-neutral-600">/forms/</span>
          <input class="slug-input flex-1" x-model="draft.slug" @input="touch()"
                 :placeholder="draft.name ? 'built from the name' : 'contact'">
        </div>
        <p class="field-error" x-show="fieldError('slug')" x-text="fieldError('slug')"></p>
      </div>

      <div class="field mt-4">
        <label>Description <span class="font-normal text-neutral-500">— a note for your team, never shown</span></label>
        <textarea class="input !min-h-[70px]" x-model="draft.description" @input="touch()"></textarea>
      </div>
    </div>

    <div class="panel p-5">
      <div class="panel-head">After it is sent</div>

      <div class="flex flex-wrap gap-4">
        <div class="field flex-[1_1_200px]">
          <label>Button label</label>
          <input class="input" x-model="draft.submit_label" @input="touch()" placeholder="Submit">
        </div>

        <div class="field flex-[2_1_260px]">
          <label>Notify by email</label>
          <input class="input" type="email" x-model="draft.notify_email" @input="touch()"
                 placeholder="hello@example.com">
          <p class="field-error" x-show="fieldError('notify_email')" x-text="fieldError('notify_email')"></p>
          <p class="mt-1 text-[11px] text-neutral-500">
            Leave blank to send nothing. A send that fails never costs the visitor their answer.
          </p>
        </div>
      </div>

      <div class="field mt-4">
        <label>Thank-you message</label>
        <textarea class="input !min-h-[70px]" x-model="draft.success_message" @input="touch()"
                  placeholder="Thanks — we have got your message."></textarea>
      </div>
    </div>
  </div>

  <div class="w-full lg:w-[300px] lg:flex-none">
    <div class="panel">
      <div class="panel-head">Status</div>
      <div class="p-4">
        <div class="field">
          <label>Accepting replies</label>
          <select class="input" x-model="draft.status" @change="touch()">
            <template x-for="status in options.statuses" :key="status">
              <option :value="status" x-text="status"></option>
            </template>
          </select>
          <p class="mt-1.5 text-[11px] leading-relaxed text-neutral-500">
            An inactive form still appears on the page, but says it is closed and refuses posts.
          </p>
        </div>

        <label class="check mt-4">
          <input type="checkbox" x-model="draft.store_submissions" @change="touch()">
          <span>Keep submissions</span>
        </label>
        <p class="mt-1.5 text-[11px] leading-relaxed text-neutral-500">
          Off, and answers are emailed and then forgotten — nothing is written to the database.
        </p>
      </div>
    </div>

    <div class="panel mt-5">
      <div class="panel-head">Putting it on a page</div>
      <div class="p-4 text-[12px] leading-relaxed text-neutral-600">
        Add the <strong class="text-ink">Contact Form</strong> section to a page, or give any library
        section a field of type <strong class="text-ink">Form</strong>, then pick this form in it.
        <button type="button" class="link mt-2 block" @click="$store.ui.go('sections')">
          Open the section library ↗
        </button>
      </div>
    </div>
  </div>
</div>
