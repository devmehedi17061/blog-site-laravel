{{-- Settings → Front end. One root element: it sits inside an x-if template. --}}
<form class="settings-stack" @submit.prevent="saveGroup('frontend')" autocomplete="off">

  {{-- content protection --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Content protection</h3>
      <p>Deterrents only. Anyone determined can still read the page source — see the note under each option.</p>
    </div>
    <div class="settings-grid settings-grid-pairs p-5">
      <template x-for="opt in [
        { key: 'disable_right_click', label: 'Disable right-click menu', note: 'Stops casual &quot;Save image as&quot;. Does not prevent downloading. Right-click still works inside form fields.' },
        { key: 'block_devtools_keys', label: 'Block F12 and Ctrl+Shift+I', note: 'Also Ctrl+U (view source). DevTools can still be opened from the browser menu.' },
        { key: 'disable_selection', label: 'Disable text selection', note: 'Stops copy-paste of body copy. Also stops visitors copying your phone number or address; form fields stay selectable.' },
        { key: 'disable_image_drag', label: 'Prevent image dragging', note: 'Stops dragging pictures to the desktop or another tab.' },
      ]" :key="opt.key">
        <label class="switch-row" :data-protection="opt.key">
          <input type="checkbox" class="switch" x-model="frontend[opt.key]" :disabled="!can.update">
          <span>
            <strong x-text="opt.label"></strong>
            <span x-html="opt.note"></span>
          </span>
        </label>
      </template>

      <label class="settings-grid-full switch-row">
        <input type="checkbox" class="switch" x-model="frontend.exempt_signed_in" :disabled="!can.update">
        <span>
          <strong>Not for people signed in to the panel</strong>
          <span>You and your editors can still inspect, select and copy on the live site. Turn off to see exactly what visitors get.</span>
        </span>
      </label>

      {{-- The usual "it does not work" is simply this switch doing its job. --}}
      <div class="settings-grid-full role-note"
           x-show="frontend.exempt_signed_in && (frontend.disable_right_click || frontend.block_devtools_keys || frontend.disable_selection || frontend.disable_image_drag)">
        <strong>Testing these?</strong> You are signed in, so the site lets you right-click, select and inspect as normal —
        visitors are still protected. To see what a visitor gets, open the site in a private (incognito) window,
        or turn off the switch above while you check.
      </div>
    </div>
  </section>

  {{-- code --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Site-wide code</h3>
      <p>Loaded on every public page, landing pages included. Not on the admin panel.</p>
    </div>
    <div class="flex flex-col gap-5 p-5">
      <div class="field">
        <label for="fe-css">Custom CSS <span class="font-normal text-neutral-500">— loaded after the site's own styles, so it wins</span></label>
        <textarea id="fe-css" class="input code-box" spellcheck="false" x-model="frontend.custom_css"
                  x-code="{ lang: 'css', readonly: !can.update }" :disabled="!can.update"></textarea>
        <p class="field-error" x-show="fieldError('custom_css')" x-text="fieldError('custom_css')"></p>
      </div>

      <div class="settings-callout" x-show="!can.scripts">
        Only a Super Admin can change the two boxes below: whatever is in them runs in every visitor's browser.
      </div>

      <div class="field">
        <label for="fe-head">Head code <span class="font-normal text-neutral-500">— before &lt;/head&gt;: Google Analytics, Tag Manager, Meta Pixel, verification tags</span></label>
        <textarea id="fe-head" class="input code-box" spellcheck="false" x-model="frontend.head_code"
                  x-code="{ lang: 'html', readonly: !can.scripts }" :readonly="!can.scripts" :disabled="!can.update"></textarea>
        <p class="field-error" x-show="fieldError('head_code')" x-text="fieldError('head_code')"></p>
      </div>

      <div class="field">
        <label for="fe-body">Body code <span class="font-normal text-neutral-500">— before &lt;/body&gt;: chat widgets and anything that can load last</span></label>
        <textarea id="fe-body" class="input code-box" spellcheck="false" x-model="frontend.body_code"
                  x-code="{ lang: 'html', readonly: !can.scripts }" :readonly="!can.scripts" :disabled="!can.update"></textarea>
        <p class="field-error" x-show="fieldError('body_code')" x-text="fieldError('body_code')"></p>
      </div>

      <p class="text-[12px] text-neutral-600" x-show="can.scripts">
        Code is added exactly as entered. A broken snippet can break the public site — check it on the site right after saving.
      </p>
    </div>
  </section>

  {{-- external files --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>External files</h3>
      <p>Stylesheets and scripts from another address — a CSS library, an icon font, a widget — loaded on every public page.</p>
    </div>
    <div class="flex flex-col gap-3 p-5">
      <div class="settings-callout" x-show="!can.scripts">
        Only a Super Admin can change this list: an external script runs in every visitor's browser.
      </div>

      <template x-for="(file, i) in frontend.external_assets" :key="i">
        <div class="ext-row">
          <span class="ext-type" :class="file.type === 'js' ? 'is-js' : 'is-css'" x-text="file.type === 'js' ? 'JS' : 'CSS'"></span>
          <div class="ext-main">
            <input class="input font-mono !text-[12px]" x-model="file.url" :readonly="!can.scripts" spellcheck="false"
                   :placeholder="file.type === 'js' ? 'https://cdn.example.com/library.min.js' : 'https://cdn.example.com/styles.min.css'"
                   :aria-label="`${file.type === 'js' ? 'Script' : 'Stylesheet'} address`">
            <div class="ext-options">
              <input class="input !h-[30px] !text-[12px] min-w-0 flex-1" x-model="file.note" :readonly="!can.scripts" placeholder="What is it for? (optional)" aria-label="Note">
              <template x-if="file.type === 'js'">
                <div class="flex gap-2">
                  <select class="input !h-[30px] !w-auto !text-[12px]" x-model="file.place" :disabled="!can.scripts" aria-label="Where">
                    <option value="head">In the head</option>
                    <option value="body">At the end of the page</option>
                  </select>
                  <select class="input !h-[30px] !w-auto !text-[12px]" x-model="file.load" :disabled="!can.scripts" aria-label="How it loads">
                    <option value="normal">Load normally</option>
                    <option value="defer">defer — after the page is read</option>
                    <option value="async">async — whenever it arrives</option>
                  </select>
                </div>
              </template>
            </div>
            <p class="field-error" x-show="fieldError(`external_assets.${i}.url`)" x-text="fieldError(`external_assets.${i}.url`) && 'Use a full address starting with https://'"></p>
          </div>
          <div class="section-row-actions" x-show="can.scripts">
            <button type="button" title="Move up" @click="moveExternal(i, -1)" :disabled="i === 0">↑</button>
            <button type="button" title="Move down" @click="moveExternal(i, 1)" :disabled="i === frontend.external_assets.length - 1">↓</button>
            <button type="button" title="Remove" @click="frontend.external_assets.splice(i, 1)">✕</button>
          </div>
        </div>
      </template>

      <p class="text-[13px] text-neutral-600" x-show="!frontend.external_assets.length">No external files. Add a stylesheet or a script by its web address.</p>
      <p class="field-error" x-show="fieldError('external_assets')" x-text="fieldError('external_assets')"></p>

      <div class="flex flex-wrap gap-2" x-show="can.scripts">
        <button type="button" class="btn btn-secondary btn-sm" @click="addExternal('css')" :disabled="frontend.external_assets.length >= 30">+ Stylesheet (CSS)</button>
        <button type="button" class="btn btn-secondary btn-sm" @click="addExternal('js')" :disabled="frontend.external_assets.length >= 30">+ Script (JS)</button>
      </div>
      <p class="text-[12px] leading-relaxed text-neutral-600">
        Loaded in this order. Stylesheets load after the site's own styles, so they can change its look; a script in the head loads
        before the page shows, one at the end after it. Only add files from services you trust.
      </p>
    </div>
  </section>

  <div class="settings-savebar" x-show="can.update">
    <span class="counter" x-show="dirty">Unsaved changes</span>
    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
    <a class="btn btn-secondary" href="/" target="_blank" rel="noopener">View site ↗</a>
    <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || !dirty"
            x-text="saving ? 'Saving…' : 'Save front end'"></button>
  </div>
</form>
