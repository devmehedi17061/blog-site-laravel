# এক CMS, অনেক site — update আর site pack

এই CMS-টা অনেকগুলো site-এ একসাথে চালানোর জন্য বানানো। প্রতিটা site-এর
তিনটা জিনিস আলাদা থাকে, আর একটা জিনিস সবার এক:

| কী | কোথায় | Update-এ বদলায়? |
|---|---|---|
| **Core** — admin panel, section builder, form, blog, security … | GitHub repo | ✅ হ্যাঁ |
| Site-এর content — page, section, header/footer, menu, form, settings, lead | ওই site-এর database | ❌ না |
| Upload, backup, `.env` | `storage/`, `.env` | ❌ না |
| Site-এর নিজস্ব code (design, importer …) | `sites/<name>/` (site pack) | ❌ না |

## Update কীভাবে সব site-এ যায়

1. Core-এ কিছু বদলালেন → GitHub-এ push করলেন।
2. নতুন version-এর tag দিলেন:
   ```
   git tag v1.2.0
   git push origin v1.2.0
   ```
3. GitHub Actions (`.github/workflows/release.yml`) নিজেই `composer install --no-dev`,
   `npm run build` করে `cms-v1.2.0.zip` আর তার `.sha256` বানিয়ে Release-এ দেয়।
4. প্রতিটা site-এর admin panel-এ **System → Updates**-এ দেখাবে
   “v1.2.0 is available”। **Back up and update** চাপলে:
   - আগে database আর বর্তমান code-এর backup নেয়,
   - zip download করে SHA-256 মিলিয়ে দেখে,
   - শুধু core file বসায় — `.env`, `storage/`, `sites/`, `public/site-build/`,
     `public/index.php`, `public/.htaccess` ছোঁয় না,
   - database migration চালায়, cache পরিষ্কার করে,
   - কোথাও আটকালে আগের code নিজেই ফেরত বসিয়ে দেয়।
5. ভুল হলে **Go back to the previous version** — আগের code ফিরে আসে।

Terminal লাগে না। Terminal থাকলে: `php artisan cms:update` (বা `--check`)।

### নিজে নিজে update
**System → Updates → Install by itself**:
- **Never** — শুধু জানাবে।
- **Security updates only** — Release-এর title বা note-এ `[security]` লেখা থাকলে
  নিজেই install করবে।
- **Every update** — সব release নিজেই install করবে।

দিনে একবার চেক করে — cron থাকলে scheduler দিয়ে
(`* * * * * php /path/artisan schedule:run`), না থাকলে কেউ admin panel খুললে।

### Private repo
Repo private হলে GitHub-এ একটা fine-grained token বানান
(এই repo, **Contents: Read-only**), তারপর **Updates → GitHub token**-এ দিন
(encrypted থাকে)। অথবা `.env`-এ `CMS_UPDATE_TOKEN=`।
অন্য repo থেকে update নিতে: `CMS_UPDATE_REPO=owner/repo`।

## Site pack
যে জিনিস admin panel দিয়ে হয় না (নিজস্ব CSS/JS build, blog-এর আলাদা design,
importer command) সেটা `sites/<name>/`-এ রাখুন — বিস্তারিত `sites/README.md`।
`.env`-এ `SITE_PACK=<name>`, তারপর একবার `npm run build:site`।

এই install-এ দুটো pack আছে (repo-তে যায় না):
- `sites/bfd` — Branding For Doctors (`bfd:install`, তার Tailwind theme আর Alpine)
- `sites/dataetl` — Data ETL (`nmc:install`, তার section আর blog design)

## Release বানানোর নিয়ম
- Version: `MAJOR.MINOR.PATCH` — bug/security fix হলে PATCH (1.1.**1**),
  নতুন feature হলে MINOR (1.**2**.0)।
- Migration সবসময় নতুন file-এ; পুরনো migration বদলাবেন না।
- `public/index.php` বা `public/.htaccess` বদলালে Release note-এ লিখে দিন —
  update ওগুলো ছোঁয় না (cPanel-এর PHP handler ওখানে থাকে)।
