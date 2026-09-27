{{-- Name, fields and the markup behind one library section. --}}
<div class="builder-col builder-col-edit">
  <template x-if="!draft">
    <div class="p-5 text-[13px] text-neutral-600">Pick a section on the left, or start a new one.</div>
  </template>

  <template x-if="draft">
    <div class="flex min-h-0 flex-1 flex-col">

      <div class="section-settings">
        <input class="input !h-[30px] flex-[1_1_150px] !text-[13px]"
               x-model="draft.name" @input="touch()" placeholder="Section name">
        <input class="input !h-[30px] flex-[2_1_200px] !text-[13px]"
               x-model="draft.description" @input="touch()" placeholder="What is it for?">
      </div>

      <p class="field-error px-3" x-show="fieldError('name')" x-text="fieldError('name')"></p>

      <div class="pane-tabs">
        <button type="button" class="pane-tab" :class="pane === 'fields' && 'pane-tab-active'" @click="pane = 'fields'">
          Fields <span class="tabular-nums" x-text="`(${fields.length})`"></span>
        </button>
        <button type="button" class="pane-tab" :class="pane === 'html' && 'pane-tab-active'" @click="pane = 'html'">HTML</button>
        <button type="button" class="pane-tab" :class="pane === 'css' && 'pane-tab-active'" @click="pane = 'css'">CSS</button>
      </div>

      @include('backend.partials.screens.library.fields')

      {{-- html --}}
      <div class="flex flex-col gap-2 overflow-y-auto p-3" x-show="pane === 'html'">
        <div class="field">
          <label>HTML</label>
          <textarea class="input !min-h-[260px] font-mono !text-[13px]" spellcheck="false" x-ref="html"
                    x-model="draft.html" @input="touch()" x-code="{ lang: 'html', tokens: () => codeTokens }"></textarea>
        </div>

        <div class="token-hints">
          <span class="token-hints-label">From the page</span>
          <template x-for="token in pageTokens" :key="token">
            <button type="button" class="token token-button" :title="`${token} — click to insert`"
                    @click="insertCode(tokenFor(token))" x-text="tokenFor(token)"></button>
          </template>
        </div>

        <div class="token-hints" x-show="fields.length > 0">
          <span class="token-hints-label">Fields</span>
          <template x-for="field in fields" :key="field.key">
            <span class="contents">
              <button type="button" class="token token-button" x-show="field.type !== 'group'"
                      :title="`${field.label} — click to insert`"
                      @click="insertCode(tokenFor(field.key))" x-text="tokenFor(field.key)"></button>

              <button type="button" class="token token-button" x-show="field.type === 'group'"
                      :title="`${field.label} — insert the repeat wrapper`"
                      @click="insertCode(groupOpen(field.key) + '\n  \n' + groupClose(field.key))"
                      x-text="groupOpen(field.key) + ' … ' + groupClose(field.key)"></button>
            </span>
          </template>
        </div>

        <div class="alert" x-show="unknownTokens.length > 0"
             x-text="`These tokens have no field: ${unknownTokens.join(', ')}. They render as nothing.`"></div>

        <div class="alert" x-show="unusedFields.length > 0"
             x-text="`These fields are never used in the HTML: ${unusedFields.join(', ')}. A page can fill them in, and nothing will show.`"></div>

        <div class="alert" x-show="suspiciousRepeats.length > 0"
             x-text="`${suspiciousRepeats.map(f => f.key + ' (' + f.uses + ' times)').join(', ')} — every slot renders the same one. Give each slot its own field.`"></div>

        <p class="text-[11px] text-neutral-500" x-show="repeatedFields.length > suspiciousRepeats.length"
           x-text="`Used more than once: ${repeatedFields.filter(f => !suspiciousRepeats.includes(f)).map(f => f.key).join(', ')}. Fine if that is deliberate.`"></p>
      </div>

      {{-- css --}}
      <div class="flex flex-col gap-2 overflow-y-auto p-3" x-show="pane === 'css'">
        <div class="field">
          <label>CSS</label>
          <textarea class="input !min-h-[260px] font-mono !text-[13px]" spellcheck="false"
                    x-model="draft.css" @input="touch()" x-code="{ lang: 'css', tokens: () => codeTokens }"></textarea>
        </div>

        <p class="text-[11px] leading-relaxed text-neutral-500">
          This CSS is printed in the page&rsquo;s <code class="font-mono">&lt;head&gt;</code> and every rule is
          rewritten to sit under that one section&rsquo;s id, so <code class="font-mono">.card</code> here can
          never style anything else on the page. Tokens work in CSS too — handy for a colour field.
        </p>
      </div>
    </div>
  </template>
</div>
