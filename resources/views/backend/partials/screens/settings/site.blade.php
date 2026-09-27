{{-- Settings → Site. One root element: it sits inside an x-if template. --}}
<form class="settings-stack" @submit.prevent="saveGroup('site')" autocomplete="off">

  {{-- brand --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Brand</h3>
      <p>The name and marks shown in the site header, the footer, browser tabs and search results.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="site-name">Site name</label>
        <input id="site-name" class="input" :class="fieldError('name') && 'input-invalid'"
               x-model="site.name" :placeholder="site.app_name" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('name')" x-text="fieldError('name')"></p>
      </div>
      <div class="field">
        <label for="site-tagline">Tagline <span class="font-normal text-neutral-500">— one line under the name</span></label>
        <input id="site-tagline" class="input" x-model="site.tagline" placeholder="What the site is about, in a few words" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('tagline')" x-text="fieldError('tagline')"></p>
      </div>

      <div class="settings-grid-full grid gap-4 sm:grid-cols-3">
        <template x-for="img in [
          { key: 'logo', label: 'Logo', hint: 'For the light header. PNG or SVG, about 40px tall.', dark: false },
          { key: 'logo_dark', label: 'Footer logo', hint: 'A light version for the dark footer. Optional.', dark: true },
          { key: 'favicon', label: 'Favicon', hint: 'The browser-tab icon. Square, 64×64 or larger.', dark: false },
        ]" :key="img.key">
          <div class="field">
            <label x-text="img.label"></label>
            <div class="image-well" :class="img.dark && 'is-dark'" :data-image="img.key">
              <img x-show="site[img.key]" :src="site[img.key]" alt="">
              <span x-show="!site[img.key]" class="text-[12px] text-neutral-500">None</span>
            </div>
            <div class="mt-1.5 flex gap-2" x-show="can.update">
              <button type="button" class="btn btn-secondary btn-sm" @click="pickImage(img.key, `Choose the ${img.label.toLowerCase()}`)"
                      x-text="site[img.key] ? 'Change' : 'Choose'"></button>
              <button type="button" class="btn btn-ghost btn-sm" x-show="site[img.key]" @click="site[img.key] = ''">Remove</button>
            </div>
            <p class="mt-1 text-[11px] text-neutral-500" x-text="img.hint"></p>
            <p class="field-error" x-show="fieldError(img.key)" x-text="fieldError(img.key)"></p>
          </div>
        </template>
      </div>

      <label class="settings-grid-full switch-row">
        <input type="checkbox" class="switch" x-model="site.show_name" :disabled="!can.update">
        <span>
          <strong>Show the site name beside the logo</strong>
          <span>Turn off when the logo already spells out the name.</span>
        </span>
      </label>
    </div>
  </section>

  {{-- contact & social --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Contact &amp; social</h3>
      <p>Shown in the site footer. Leave anything blank to leave it out.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="site-contact-email">Contact email</label>
        <input id="site-contact-email" type="email" class="input" :class="fieldError('contact_email') && 'input-invalid'"
               x-model="site.contact_email" placeholder="hello@yourdomain.com" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('contact_email')" x-text="fieldError('contact_email')"></p>
      </div>
      <div class="field">
        <label for="site-phone">Phone</label>
        <input id="site-phone" class="input" :class="fieldError('phone') && 'input-invalid'"
               x-model="site.phone" placeholder="+880 1700 000000" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('phone')" x-text="fieldError('phone')"></p>
      </div>
      <div class="field settings-grid-full">
        <label for="site-address">Address</label>
        <textarea id="site-address" class="input !min-h-[64px]" x-model="site.address" :disabled="!can.update"></textarea>
        <p class="field-error" x-show="fieldError('address')" x-text="fieldError('address')"></p>
      </div>

      <template x-for="net in options.socials" :key="net.key">
        <div class="field">
          <label :for="`site-social-${net.key}`" x-text="net.label"></label>
          <input :id="`site-social-${net.key}`" type="url" class="input" :class="fieldError(`social_${net.key}`) && 'input-invalid'"
                 x-model="site[`social_${net.key}`]" :placeholder="`https://…`" :disabled="!can.update">
          <p class="field-error" x-show="fieldError(`social_${net.key}`)" x-text="fieldError(`social_${net.key}`)"></p>
        </div>
      </template>
    </div>
  </section>

  {{-- SEO --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>SEO defaults</h3>
      <p>Used on every page that does not set its own.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="site-title-format">Page title format</label>
        <input id="site-title-format" class="input font-mono" :class="fieldError('title_format') && 'input-invalid'"
               x-model="site.title_format" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('title_format')" x-text="fieldError('title_format')"></p>
        <p class="mt-1 text-[11px] text-neutral-500">
          <span class="font-mono">{title}</span> is the page, <span class="font-mono">{site}</span> the site name.
          Looks like: <strong class="text-ink" x-text="titlePreview"></strong>
        </p>
      </div>
      <div class="field">
        <label>Default share image</label>
        <div class="flex items-center gap-2">
          <div class="image-well !h-[48px] !w-[86px] flex-none" data-image="share_image">
            <img x-show="site.share_image" :src="site.share_image" alt="">
          </div>
          <button type="button" class="btn btn-secondary btn-sm" x-show="can.update" @click="pickImage('share_image', 'Choose the default share image')"
                  x-text="site.share_image ? 'Change' : 'Choose'"></button>
          <button type="button" class="btn btn-ghost btn-sm" x-show="can.update && site.share_image" @click="site.share_image = ''">Remove</button>
        </div>
        <p class="mt-1 text-[11px] text-neutral-500">Shown when a page is shared and has no featured image. 1200×630 works everywhere.</p>
        <p class="field-error" x-show="fieldError('share_image')" x-text="fieldError('share_image')"></p>
      </div>
      <div class="field settings-grid-full">
        <label for="site-description">Default description</label>
        <textarea id="site-description" class="input !min-h-[64px]" x-model="site.default_description" maxlength="300" :disabled="!can.update"></textarea>
        <p class="mt-1 text-[11px] text-neutral-500"><span x-text="(site.default_description || '').length"></span>/300 — Google shows about 155 characters.</p>
      </div>
      <div class="field">
        <label for="site-google">Google Search Console code</label>
        <input id="site-google" class="input font-mono" :class="fieldError('google_verification') && 'input-invalid'"
               x-model="site.google_verification" placeholder="Paste the code or the whole <meta> tag" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('google_verification')" x-text="fieldError('google_verification')"></p>
      </div>
      <div class="field">
        <label for="site-bing">Bing Webmaster code</label>
        <input id="site-bing" class="input font-mono" :class="fieldError('bing_verification') && 'input-invalid'"
               x-model="site.bing_verification" placeholder="Paste the code or the whole <meta> tag" :disabled="!can.update">
        <p class="field-error" x-show="fieldError('bing_verification')" x-text="fieldError('bing_verification')"></p>
      </div>
      <div class="field settings-grid-full">
        <label for="site-robots">robots.txt <span class="font-normal text-neutral-500">— rules for search engine crawlers</span></label>
        <textarea id="site-robots" class="input code-box !min-h-[90px]" spellcheck="false" x-model="site.robots_txt" :disabled="!can.update"></textarea>
        <p class="mt-1 text-[11px] text-neutral-500">
          Served at <a href="/robots.txt" target="_blank" rel="noopener" class="text-accent-700 underline">/robots.txt</a>.
          The line pointing to your <a href="/sitemap.xml" target="_blank" rel="noopener" class="text-accent-700 underline">sitemap</a> is added automatically.
        </p>
        <p class="field-error" x-show="fieldError('robots_txt')" x-text="fieldError('robots_txt')"></p>
      </div>

      <label class="settings-grid-full switch-row">
        <input type="checkbox" class="switch" x-model="site.noindex" :disabled="!can.update">
        <span>
          <strong>Hide the whole site from search engines</strong>
          <span>Adds "noindex" to every page. For a site still being built — remember to turn it off at launch.</span>
        </span>
      </label>
    </div>
  </section>

  {{-- locale & maintenance --}}
  <section class="panel">
    <div class="settings-panel-head">
      <h3>Locale &amp; maintenance</h3>
      <p>How dates read on the site, and a way to close it for a while.</p>
    </div>
    <div class="settings-grid p-5">
      <div class="field">
        <label for="site-language">Site language</label>
        <select id="site-language" class="input" x-model="site.language" :disabled="!can.update">
          <template x-for="l in options.languages" :key="l.value">
            <option :value="l.value" x-text="l.label" :selected="l.value === site.language"></option>
          </template>
        </select>
        <p class="mt-1 text-[11px] text-neutral-500">The language your content is written in. Tells search engines and screen readers, and writes dates in it. The admin panel stays in English.</p>
        <p class="field-error" x-show="fieldError('language')" x-text="fieldError('language')"></p>
      </div>
      <div class="field">
        <label for="site-timezone">Timezone</label>
        <select id="site-timezone" class="input" x-model="site.timezone" :disabled="!can.update">
          <template x-for="zone in options.timezones" :key="zone">
            <option :value="zone" x-text="zone.replaceAll('_', ' ')" :selected="zone === site.timezone"></option>
          </template>
        </select>
        <p class="mt-1 text-[11px] text-neutral-500">Dates on the site are shown in this zone. Times are still stored in UTC.</p>
      </div>
      <div class="field">
        <label for="site-date-format">Date format</label>
        <select id="site-date-format" class="input" x-model="site.date_format" :disabled="!can.update">
          <template x-for="f in options.date_formats" :key="f.value">
            <option :value="f.value" x-text="f.label" :selected="f.value === site.date_format"></option>
          </template>
        </select>
      </div>

      <label class="settings-grid-full switch-row" :class="site.maintenance && 'is-warning'">
        <input type="checkbox" class="switch" x-model="site.maintenance" :disabled="!can.update">
        <span>
          <strong>Maintenance mode</strong>
          <span>Visitors see a "back soon" page (HTTP 503, which search engines treat as temporary). You still see the site while signed in.</span>
        </span>
      </label>
      <div class="field settings-grid-full" x-show="site.maintenance">
        <label for="site-maintenance-message">Message for visitors</label>
        <textarea id="site-maintenance-message" class="input !min-h-[64px]" x-model="site.maintenance_message" :disabled="!can.update"></textarea>
      </div>
    </div>
  </section>

  <div class="settings-savebar" x-show="can.update">
    <span class="counter" x-show="dirty">Unsaved changes</span>
    <button type="button" class="btn btn-ghost btn-sm" x-show="dirty" @click="discard()">Undo</button>
    <button type="submit" class="btn btn-primary ml-auto" :disabled="saving || !dirty"
            x-text="saving ? 'Saving…' : 'Save site settings'"></button>
  </div>
</form>
