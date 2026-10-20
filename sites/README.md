# Site packs

A site pack is everything that makes **one** site different from the shared
CMS core and cannot be done in the admin panel: its own views, CSS, JavaScript,
PHP classes and artisan commands. Pages, sections, menus, header/footer, forms
and settings are **not** in a pack — they live in that site's database.

```
sites/<name>/
  resources/views/         views that replace the core's (same path), e.g.
                           resources/views/frontend/blog/index.blade.php
  resources/css/*.css      the site's stylesheet(s)   ─┐ built by
  resources/js/*.js        the site's script(s)        ─┘ npm run build:site
                           (files starting with "_" are imported partials)
  src/                     PHP classes, namespace Sites\<Name>\
  routes/console.php       artisan commands (e.g. an importer)
  database/                anything else the pack needs
```

Switch it on in that site's `.env`:

```
SITE_PACK=<name>
# optional: the pack's set-up command, run by the web installer
SITE_PACK_INSTALL=<name>:install
```

then build its assets once: `npm run build:site` (into `public/site-build/`).

A core update (Settings → Updates) never touches `sites/`, `public/site-build/`,
`.env`, `storage/` or the database — so a pack survives every update.

Packs are **not** stored in the core repository (`/sites/*` is ignored). Keep
each one with its site, or in its own (private) repository.
