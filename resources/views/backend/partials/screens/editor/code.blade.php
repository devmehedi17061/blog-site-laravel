{{-- The Code tab, shared by the page and post editors: this one page's own
     CSS and JavaScript. `noun` is 'page' or 'post'. --}}
<div class="flex flex-col gap-5">
  <div class="panel p-5">
    <div class="field">
      <label class="flex items-baseline gap-2">
        CSS <span class="font-normal text-neutral-500">— printed in this {{ $noun }}'s head, after the site's styles</span>
      </label>
      <textarea x-model="draft.custom_css" x-code="{ lang: 'css' }" spellcheck="false" class="input"></textarea>
      <p class="field-error" x-show="fieldError('custom_css')" x-text="fieldError('custom_css')"></p>
      <p class="mt-2 text-[12px] leading-relaxed text-neutral-600">
        Only this {{ $noun }} gets it. For the whole site use Settings → Front end → Custom CSS; for colours, fonts and widths, Settings → Design.
      </p>
    </div>
  </div>

  <div class="panel p-5">
    <div class="field">
      <label class="flex items-baseline gap-2">
        JavaScript <span class="font-normal text-neutral-500">— runs at the end of this {{ $noun }}, after everything else has loaded</span>
      </label>
      <textarea x-model="draft.custom_js" spellcheck="false" class="input"
                x-code="{ lang: 'js', readonly: $store.auth.user?.role?.slug !== 'super-admin' }"></textarea>
      <p class="field-error" x-show="fieldError('custom_js')" x-text="fieldError('custom_js')"></p>
      <p class="mt-2 text-[12px] leading-relaxed text-neutral-600" x-show="$store.auth.user?.role?.slug === 'super-admin'">
        Write plain JavaScript without a <code class="font-mono">&lt;script&gt;</code> tag. It runs in every visitor's browser, so only add code you trust.
      </p>
      <p class="mt-2 role-note" x-show="$store.auth.user?.role?.slug !== 'super-admin'">
        Only a Super Admin can add or change JavaScript, because it runs in every visitor's browser. You can still edit the CSS.
      </p>
    </div>
  </div>
</div>
