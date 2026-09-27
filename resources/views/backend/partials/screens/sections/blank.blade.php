{{-- A blank section: markup written for this page and nowhere else. --}}
<div class="flex flex-col gap-2 overflow-y-auto p-3" x-show="pane === 'html' && !isLibrarySection">
  <div class="field">
    <label>HTML</label>
    <textarea class="input !min-h-[260px] font-mono !text-[13px]" spellcheck="false"
              x-model="activeSection.html" @input="touch()" x-code="{ lang: 'html' }"></textarea>
  </div>

  <p class="text-[11px] leading-relaxed text-neutral-500">
    Plain HTML — no fields here. If this section is worth reusing, build it in the
    <button type="button" class="link" @click="$store.ui.go('sections')">section library</button>
    instead and add it to as many pages as you like.
  </p>
</div>

<div class="flex flex-col gap-2 overflow-y-auto p-3" x-show="pane === 'css' && !isLibrarySection">
  <div class="field">
    <label>CSS</label>
    <textarea class="input !min-h-[260px] font-mono !text-[13px]" spellcheck="false"
              x-model="activeSection.css" @input="touch()" x-code="{ lang: 'css' }"></textarea>
  </div>

  <p class="text-[11px] leading-relaxed text-neutral-500">
    Printed in the page&rsquo;s <code class="font-mono">&lt;head&gt;</code>, with every rule rewritten to sit
    under this section&rsquo;s id — so it styles this section and nothing else on the page.
  </p>
</div>
