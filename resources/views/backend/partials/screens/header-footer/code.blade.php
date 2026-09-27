{{-- The HTML and CSS tabs, with one-click inserts for the site tokens. --}}

{{-- html --}}
<div class="flex flex-col gap-3 overflow-y-auto p-3" x-show="pane === 'html'">
  <div class="hfb-insert">
    <div class="hfb-insert-head">
      <strong>Insert</strong>
      <span>Click to put it where the cursor is — or type <code class="font-mono">{{ '{' }}{{ '{' }}</code> in the HTML to pick from a list.</span>
    </div>
    <template x-for="group in tokenGroups" :key="group.label">
      <div class="hfb-insert-row">
        <span class="hfb-insert-label" x-text="group.label"></span>
        <template x-for="token in group.items" :key="token.key">
          <button type="button" class="hfb-token" :class="usesToken(token.key) && 'is-used'" :title="token.hint"
                  @click="insertToken(token.key)">
            <span x-text="token.label"></span>
          </button>
        </template>
      </div>
    </template>
    <div class="hfb-insert-row" x-show="fields.length > 0">
      <span class="hfb-insert-label">Fields</span>
      <template x-for="field in fields" :key="field.key">
        <button type="button" class="hfb-token is-field" :title="`${field.label} — ${field.type}`"
                @click="field.type === 'group' ? insert(groupOpen(field.key) + '\n' + groupClose(field.key)) : insertToken(field.key)">
          <span x-text="field.label || field.key"></span>
        </button>
      </template>
    </div>
  </div>

  <div class="field">
    <label>HTML</label>
    <textarea x-ref="html" class="input !min-h-[300px] font-mono !text-[13px]" spellcheck="false"
              x-model="draft.html" @input="touch()" x-code="{ lang: 'html', tokens: () => codeTokens }"></textarea>
  </div>

  <div class="alert" x-show="unknownTokens.length > 0"
       x-text="`These tokens are not a site token or a field: ${unknownTokens.join(', ')}. They render as nothing.`"></div>
  <div class="alert" x-show="unusedFields.length > 0"
       x-text="`These fields are never used in the HTML: ${unusedFields.join(', ')}.`"></div>
  <p class="text-[12px] leading-relaxed text-neutral-600" x-show="draft.kind === 'header' && !usesToken('menu_toggle')">
    Tip: add the <strong>☰ Menu button</strong> and give the element holding your menu <code class="font-mono">data-menu-panel</code>,
    so phones get a menu they can open.
  </p>
</div>

{{-- css --}}
<div class="flex flex-col gap-2 overflow-y-auto p-3" x-show="pane === 'css'">
  <div class="field">
    <label>CSS</label>
    <textarea class="input !min-h-[320px] font-mono !text-[13px]" spellcheck="false" x-model="draft.css" @input="touch()" x-code="{ lang: 'css', tokens: () => codeTokens }"></textarea>
  </div>
  <div class="hfb-help">
    <p>Every rule is kept inside this <span x-text="noun"></span>, so <code>.wrap</code> here never styles anything else on the page.</p>
    <p><code>:root</code> means the <span x-text="noun"></span> itself. The ☰ button adds <code>menu-open</code> to it, so
      <code>:root.menu-open .nav &#123; display: flex; &#125;</code> shows the menu it opened.</p>
    <p>Use <code>&#64;media (max-width: 900px) &#123; … &#125;</code> for tablets and <code>600px</code> for phones, and check each size with the buttons above the preview.
      Field tokens work here too — handy for a colour field.</p>
  </div>
</div>
