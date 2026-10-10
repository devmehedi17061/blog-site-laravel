# LMS

A Laravel **learning management system** built on the blog CMS from
`Northwind Admin Panel.html`. One Laravel application serving three faces:

- **Public site** — server-rendered Blade: the course catalogue, course pages, the blog
  and builder pages, all indexable.
- **Student area** — sign up, buy or join courses, learn lesson by lesson, track
  progress, review, and print a certificate.
- **Admin panel** — Alpine.js + Tailwind behind a login at `/admin`: courses and their
  curriculum, enrollments and payments, students, plus everything the CMS already had.

The LMS is in the [LMS](#lms) section below. In Bangla:
[docs/lms-guide-bn.md](docs/lms-guide-bn.md) (using the LMS) and
[docs/install-deploy-bn.md](docs/install-deploy-bn.md) (install and go live).

## Installing a fresh copy

Create an empty MySQL database, copy `.env.example` to `.env` and fill in the `DB_`
lines, then:

```bash
composer setup   # composer + npm install, build, then php artisan cms:install
```

`cms:install` runs the migrations, adds the section library, header and footer
designs, the contact form and a Home/Blog menu, links the uploads folder, and asks
for your site name and first Super Admin. It is safe to run again. Add `--demo` for
the sample content and demo accounts below (refused when `APP_ENV=production`).

Step-by-step install, going live on cPanel or a VPS, and moving content:
[docs/install-deploy-bn.md](docs/install-deploy-bn.md) (Bangla).

## Running it

Start MySQL from the XAMPP Control Panel, then two terminals:

```bash
php artisan serve --port=8080   # http://localhost:8080
npm run dev                     # Vite, for hot reload while editing assets
```

Port 8080 is used because two other XAMPP projects already sit on 8000. Change it in
`.env` (`APP_URL`) if you move it.

`npm run dev` is only needed while working on CSS/JS. For a plain run, `npm run build`
once is enough.

| URL | What |
| --- | --- |
| `/` | The page chosen as the homepage, falling back to the built-in course homepage |
| `/courses` | Course catalogue — search, topic, level, free/paid, sort |
| `/courses/{slug}` | A course's page: curriculum, reviews, the buy box |
| `/learn/{slug}/{lesson?}` | The course player |
| `/login`, `/register` | Student sign-in and sign-up |
| `/my-courses`, `/my-account` | The student area |
| `/{slug}` | Any other published page |
| `/blog` | Public blog |
| `/blog/{slug}` | A post |
| `/category/{slug}` | Posts in a category |
| `/admin` | Admin panel (redirects to login) |
| `/admin/login` | Sign in |

### Built files

`npm run build` writes plain, readable names to `public/build/`:

```
public/build/css/app.css    one stylesheet for the site and the admin
public/build/js/site.js     the public site
public/build/js/admin.js    the admin panel
```

There is no hash in the file names. Laravel adds one to each URL instead
(`/build/css/app.css?v=626157fa17`, see `AppServiceProvider`), taken from
the file's content, so browsers fetch the new file after every build.
File names in `resources/` are kebab-case throughout.

## Sign in

With `cms:install --demo` (local only; a clean install uses the admin you chose):

| Email | Password | Role |
| --- | --- | --- |
| arif@example.com | password | Super Admin |
| mira@example.com | password | Editor |
| nadia@example.com | password | Author — own content only |
| tanvir@example.com | password | Viewer, suspended — login is blocked on purpose |
| rafiq@example.com | password | Instructor — own courses only |
| student@example.com | password | Student (Sadia) — sign in at `/login`, not `/admin` |
| karim@example.com | password | Student (Karim) |

## Layout

```
lms-site-laravel/
├── app/
│   ├── Actions/Posts/SavePost.php      create + update rules in one place
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                  login, panel, JSON endpoints
│   │   │   └── Site/                   public blog
│   │   ├── Requests/                   one small class per operation
│   │   └── Resources/                  JSON shape per model
│   ├── Models/Concerns/Filterable.php  search + whitelisted sorting
│   ├── Policies/PostPolicy.php         permission + ownership
│   ├── Support/Slug.php                unique slug generation
│   └── View/Composers/MenuComposer.php public header navigation
├── resources/
│   ├── css/
│   │   ├── app.css                 entry — imports everything below, in order
│   │   ├── core/                   shared by the site AND the admin
│   │   │   ├── tokens.css          palette, three border tiers, elevation
│   │   │   ├── base.css            type scale, focus rings, scrollbars
│   │   │   ├── components.css      .panel .data-table .chip .btn .input .tag …
│   │   │   └── content.css         rich text: the editor AND the public post
│   │   ├── site/                   the public site
│   │   │   ├── layout.css          site width and the :root design defaults
│   │   │   ├── navigation.css      the public menu, three levels
│   │   │   ├── header-footer.css   site tokens a header or footer can use
│   │   │   ├── language-switch.css the বাংলা / EN switch
│   │   │   ├── slider.css          the slider engine's structure
│   │   │   ├── forms.css           forms built with the form builder (.cms-*)
│   │   │   ├── courses.css         course cards, price, stars, curriculum
│   │   │   ├── lesson-player.css   the player, quizzes, lesson Q&A
│   │   │   └── student-panel.css   student panel, receipts, the certificate
│   │   └── admin/                  one file per admin screen
│   │       ├── dashboard.css  post-editor.css  media-library.css
│   │       ├── section-builder.css  header-footer-builder.css
│   │       ├── form-builder.css  form-inbox.css  menu-builder.css
│   │       ├── course-editor.css  reports.css  roles.css
│   │       └── security.css  revisions.css  settings.css
│   ├── js/
│   │   ├── site.js                     the public site (slider, menu toggle)
│   │   ├── admin.js                    Alpine bootstrap for the panel
│   │   └── admin/
│   │       ├── api.js                  fetch wrapper, CSRF, upload, ApiError
│   │       ├── lib/                    resource-table, media-browser, rich-editor,
│   │       │                            post-image, chunk-upload, code-editor,
│   │       │                            seo, seo-panel, tree-list, upload, debounce
│   │       ├── lib/sections/           the section renderer's client twin
│   │       ├── lib/forms/              field types for the form builder
│   │       ├── stores/                 auth (can()), ui (routing, toasts), picker
│   │       └── screens/                one file per admin screen, named like
│   │                                    its route: courses, course-editor,
│   │                                    payments, coupons, reports, posts …
│   └── views/
│       ├── backend/                    admin layout, login, panel
│       │   └── partials/
│       │       ├── layout/             header, sidebar, footer, toast
│       │       └── screens/            dashboard, posts, post-editor
│       │           └── editor/         toolbar, image-bar, sidebar, seo
│       └── frontend/                   public blog, SEO-friendly Blade
└── routes/
    ├── web.php                         public site + form posts, requires admin.php
    ├── admin.php                       /admin pages + login
    └── admin/                          JSON endpoints the panel calls
        ├── overview.php
        ├── content.php
        ├── media.php
        ├── pages.php
        ├── forms.php
        ├── menu.php
        └── categories.php
```

## How auth works

Plain Laravel **session auth** — no tokens, no CORS, because everything is one origin.

- `/admin/login` is a normal Blade form. It works without JavaScript.
- `PanelController` prints the signed-in user into the page as `window.__ADMIN__`,
  so the panel paints without a round trip and Alpine never touches credentials.
- The Alpine screens call `/admin/api/*` with the CSRF token from the `<meta>` tag.
  A 401 or 419 bounces the user back to the login form.
- Sanctum is still installed but unused. It is there if you later want a token API
  for a mobile app; nothing breaks if you remove it.

## Database

`lms_site_laravel` on MySQL. Reset and reseed:

```bash
php artisan migrate:fresh --seed
```

| Module | Tables |
| --- | --- |
| Access | `users`, `roles`, `permissions`, `permission_role` |
| Content | `pages`, `posts`, `categories`, `media`, `media_folders` |
| Sections | `section_templates` (the library), `sections` (a page's instances) |
| Menu | `menu_items` |
| Builders | `forms`, `form_fields`, `form_submissions` |
| System | `settings`, `ip_rules`, `activity_logs` |
| LMS | `courses`, `course_sections`, `lessons`, `enrollments`, `lesson_completions`, `course_reviews` |

Permissions follow `module.ability` — `posts.create`, `users.delete`. The sidebar hides
any entry the signed-in role cannot view, and a group with no visible entries hides too.

## LMS

### What is where

| | Public | Admin |
| --- | --- | --- |
| Courses | `/courses`, `/courses/{slug}` | **LMS → Courses**: list, then a full-page editor (Details · Curriculum · SEO) |
| Lessons | `/learn/{slug}/{lesson}` | The editor's **Curriculum** tab: sections of lessons, one modal per lesson |
| Payments | `/courses/{slug}/checkout` | **LMS → Enrollments**: Pending sign-ups, Approve / Cancel / Enroll a student |
| Students | `/register`, `/my-courses`, `/my-account` | **LMS → Students**, and User management for passwords and suspensions |
| Rules | — | **Settings → LMS**: currency, payment methods and instructions, auto-approve, sign-up, reviews, certificates |

Courses share the blog's **Categories**, so one tree files both. Deleted courses go to the
**Trash** (with posts, pages and media) and come back with every student's place and progress.

### One account system, two doors

Students and staff are both `users`. The **Student** role (locked, no permissions) is what
tells them apart:

- `/admin/login` refuses student accounts, and the `staff` middleware sends a signed-in
  student who opens `/admin` to `/my-courses` (JSON calls get 403).
- `/login` is the students' door. Staff can use it too and land on their own courses, but
  an account with two-factor on must use `/admin/login`, the only place that asks for the code.
- Guests sent to sign in go to the right door: `/admin/*` → `/admin/login`, anything else → `/login`.

### Roles

| Role | Courses | Enrollments |
| --- | --- | --- |
| Super Admin, Editor | everything | everything |
| Instructor (`Own content`) | create, edit **their own** (cannot delete, cannot reassign) | see students on **their own** courses |
| Student | — | — (public site only) |

Permissions are `courses.*` and `enrollments.*`, group **LMS** under Roles & permissions.
Migration `2026_10_06_090100` adds them to every role that could already edit pages.

### Selling a course

There is no card gateway. A paid course works the way most Bangladeshi course sites do:

1. The student opens **Buy this course** → checkout, sees **Settings → LMS → Payment
   instructions**, pays by bKash / Nagad / bank, and types the method and transaction ID.
2. The enrollment is **Pending**. It shows on the dashboard ("waiting for approval"), as a
   sidebar badge, and first in **LMS → Enrollments**.
3. You check the payment and press **Approve**; the course opens for the student.

Turn on **Open paid courses straight away** to skip step 3. A price of `0` (or a sale price of
`0`) makes a course free: one click to join. **Enroll a student** adds anyone by email — a
new email gets a student account and a password shown once.

### Lessons

A lesson is a **video** (YouTube, Vimeo — ordinary watch links are fine — or an `.mp4`
address) or an **article**, with rich-text notes, an optional download from the media
library, a length in minutes, and a **Free preview** switch that opens it to everyone.
`Lesson::embedUrl()` turns links into embeds (YouTube through `youtube-nocookie.com`).

**Private uploaded videos.** YouTube *Private* videos cannot be embedded anywhere, so a
video lesson can instead be an uploaded file:

- The editor sends it in 5 MB chunks (`lib/chunk-upload.js` →
  `POST /admin/api/courses/{id}/lesson-video`), so PHP's `upload_max_filesize` never matters;
  the ceiling is **Settings → LMS → Largest video upload** (2 GB by default).
- `App\Support\LessonVideos` joins the chunks, checks the bytes really are a video, and
  stores it on the private `lessons` disk (`storage/app/private/lessons/{course}/…`). Nothing
  in `public/` points at it.
- `GET /learn/{slug}/{lesson}/video` streams it after the same check as the lesson page
  (active enrollment, staff who can edit, or a free preview), with Range support so the
  player can seek.
- A lesson only accepts a path its own course uploaded. Replaced and removed videos are
  deleted on save; uploads never attached are swept after a day; deleting a course for good
  deletes its folder.
- `storage/app/private/lessons` is not in Git — copy it along with the database when
  moving the site.

The player hides the download button and right-click menu, but a determined student can
still capture what plays on their screen; that is true of every video site.

The curriculum is saved as one tree (`PUT /admin/api/courses/{id}/curriculum`) in one
transaction, like a page's sections. Existing rows keep their ids, so **renaming or
reordering never loses student progress**; only lessons removed from the tree are deleted.
Ids that belong to another course are never reused.

### Progress and certificates

`lesson_completions` holds one row per finished lesson. When the last one is ticked,
`enrollments.completed_at` is set (`Enrollment::syncCompletion()`, also re-run when the
curriculum changes) and `/certificate/{enrollment}` opens: a printable A4 landscape page —
"Save as PDF" in the print dialog makes the file. Only the student and staff who can see the
enrollment may open it.

### Coupons

`coupons` (+ `coupon_course`, empty = every course) and `coupon_redemptions`, one row per
use. `Coupon::problemFor($user, $course)` is the single rule for whether a code applies:
active, inside its dates, for this course, above `min_amount`, under `max_uses` and
`max_uses_per_user`. A use waiting on the same student's own pending payment for the same
course does not count, because resending replaces it.

- At checkout the code is kept in the session per course (`coupons.{id}`) and checked again
  on every request; `?coupon=CODE` on a course page or the catalogue applies it from a link.
- `Lms::submitPayment()` stores `coupon_id`, `discount` and the discounted `expected_amount`
  on the payment and records the redemption; `rejectPayment()` deletes it (the use comes back).
- A 100% coupon skips payment: `Lms::enrollWithFreeCoupon()`.
- Permissions `coupons.*` (Super Admin, Editor; not Instructor).

### Quizzes

A lesson of type `quiz` has `quiz_questions` (`single`, `multiple`, `true_false`; options are
`[{text, correct}]`), plus `pass_mark`, `max_attempts` (0 = unlimited) and `shuffle_questions`.
They are saved with the curriculum tree. `QuizController` grades on the server and stores a
`quiz_attempts` row; a pass is the only way a quiz lesson becomes complete (the complete
endpoint refuses quizzes), so with "Lessons in order" the next lesson waits for a pass.
Right answers are only revealed after a pass or when no tries are left. Staff who can edit
the course can try a quiz without it being recorded.

### Q&A, announcements, wishlist, certificate check

- `discussions`: a question has no `parent_id`; replies point at it. A reply from staff who
  can edit the course marks it answered and notifies the asker. Admin: LMS → Q&A.
- `course_announcements`: sent from the course editor's Announcements tab to every active
  student as a database notification (no mass email), and shown on the course page.
- `wishlists`: the ♥ on a course page; Student panel → Wishlist.
- `/certificates/verify?serial=…` is public. `Enrollment::certificateSerial()` ends in an
  HMAC fragment (app key), so serials cannot be guessed from an id and a date.

### Learning flow and admin tools

- The player marks a video lesson complete in the background at 90% watched (YouTube
  IFrame API or the `<video>` element), remembers the position in `localStorage`, and
  offers the next lesson in an "Up next" overlay. On a phone a bar at the bottom holds
  previous / complete / next. Keys: `N` next, `P` previous, `M` mark complete, `F` focus.
- The student dashboard has a getting-started checklist, a week chart and a streak.
- Payments: tick several and verify them at once (`POST /payments/bulk-verify`), or
  download the filtered list as CSV (`GET /payments/export`). Students export the same way.
- Courses and lessons can be duplicated; curriculum rows are dragged into order.
- LMS → Reports: revenue and enrollments by month, per-course completion and rating,
  payment methods, coupon use and the hardest quiz questions (`ReportController`).

### Languages (English / বাংলা)

The student side speaks English and Bangla; the admin panel stays English.

- `SetSiteLanguage` picks the language: the visitor's choice this session
  (`/language/bn`, `/language/en`), else `users.locale`, else Settings → Site → Language.
- Every student-facing string goes through `__()`; the Bangla lines are in `lang/bn.json`
  (plus `lang/bn/validation.php`, `auth.php`, `passwords.php`, `pagination.php`).
  `LanguageTest` fails if a key in the code has no Bangla line.
- Text typed into the Section library, Header & footer and form fields also goes through
  `__()`, so a starter's "Quick links" or "Home" is translated when `bn.json` has it;
  anything else shows as written.
- `StudentNotice` stores English templates plus `params` and is worded when read — in the
  panel in the current language, by email in the student's own (`User::preferredLocale()`).
- To add a language: copy `lang/bn.json` to `lang/<code>.json`, translate it, and add the
  code to `SetSiteLanguage::OFFERED`, `SiteSettings::LANGUAGES` and the `/language/{locale}` route.

### Admin JSON endpoints (LMS)

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/courses` | Paged list — `search`, `status`, `category_id`, `sort`, `direction`, `per_page` |
| GET | `/courses/options` | Statuses, levels, categories, instructors, currency |
| POST / GET / PUT / DELETE | `/courses`, `/courses/{id}` | Create, read, update, move to trash |
| GET / PUT | `/courses/{id}/curriculum` | Read, or replace the whole tree |
| GET | `/enrollments` | Paged — `search`, `status`, `course_id`; returns a per-status `summary` |
| GET | `/enrollments/options` | Courses, payment methods, currency |
| POST | `/enrollments` | Enroll by email (makes a student account when needed) |
| PUT / DELETE | `/enrollments/{id}` | Approve / cancel / edit payment, or remove |
| GET | `/students` | Student accounts with active, pending and completed counts |
| PUT | `/settings/lms` | Settings → LMS |

### Tests

```bash
php artisan test
```

`tests/Feature/LmsTest.php`, `PaymentTest.php`, `LessonVideoTest.php`, `CouponTest.php` and
`QuizAndCommunityTest.php`, `AdminToolsTest.php` and `LanguageTest.php` (80 tests) cover the rules above: the admin door, drafts, previews,
free and paid enrollment, auto-approve, completion and certificates, reviews, instructor
scope, the curriculum tree, enrollments, settings, the trash, reports and exports, and the
Bangla interface.

## Admin JSON endpoints

All under `/admin/api`, all behind `auth`.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/dashboard` | Stat tiles, activity feed, system rows, sidebar counts |
| GET | `/posts` | Paged list — `search`, `status`, `category_id`, `sort`, `direction`, `per_page` (5–100), `page` |
| GET | `/posts/options` | Statuses, categories and authors for the post form |
| GET | `/media` | Paged library — `search`, `folder`, `kind`, `sort`, `direction`, `per_page` (12–96) |
| GET | `/media/{id}` | One file; add `?with_usage=1` for the posts that reference it |
| POST | `/media` | Upload — `files[]`, up to 20 at a time, optional `folder_id` |
| PUT | `/media/{id}` | Rename, alt text, caption, move to a folder |
| DELETE | `/media/{id}` | Delete one file |
| POST | `/media/bulk` | `{action: "move"\|"delete", ids: [], folder_id}` |
| GET/POST | `/media/folders` | List and create folders |
| GET | `/pages` | Paged list — `search`, `status`, `sort`, `direction`, `per_page` |
| GET | `/pages/options` | Statuses, page templates, section modes, field types, authors |
| POST | `/pages` | Create, filling sections from the chosen template |
| GET/PUT/DELETE | `/pages/{id}` | Read, update, delete |
| GET/PUT | `/pages/{id}/sections` | Read, or replace the whole section list in one transaction |
| GET/POST | `/section-templates` | The shared library — list (with `used_by` counts) and create |
| GET/PUT/DELETE | `/section-templates/{id}` | Read, update or delete one library section |
| PUT/DELETE | `/media/folders/{id}` | Rename or delete a folder |
| GET | `/categories` | The whole tree, flat and in order, with post counts |
| POST | `/categories` | Create one |
| GET/PUT/DELETE | `/categories/{id}` | Read, update, or delete with `move_posts_to` |
| PUT | `/categories/order` | Replace the tree's order and nesting in one transaction |
| GET | `/menu` | One location's menu, flat and in order — `location` |
| PUT | `/menu` | Replace that location's whole menu in one transaction |
| GET | `/menu/options` | Locations, visibilities, the icon set, and somewhere to link to |
| GET | `/forms` | Paged list — `search`, `status`, `sort`, `direction`, `per_page` |
| GET | `/forms/options` | Field types, widths and statuses for the builder |
| POST | `/forms` | Create a form, fields and all |
| GET/PUT/DELETE | `/forms/{id}` | Read, update (fields included), delete |
| GET | `/forms/{id}/submissions` | Paged submissions, answers resolved against the current fields |
| GET | `/forms/{id}/submissions/export` | Streamed CSV of every submission |
| DELETE | `/forms/{id}/submissions/{id}` | Delete one submission |
| GET | `/inbox` | Every form's submissions — `form`, `state`, `search`, `per_page`; returns an unread `summary` |
| GET | `/inbox/{id}` | One submission; reading it is what marks it read |
| PUT | `/inbox/{id}` | `{unread: true\|false}` |
| DELETE | `/inbox/{id}` | Delete one |
| POST | `/inbox/bulk` | `{action: "read"\|"unread"\|"delete", ids: []}` |
| POST | `/inbox/read-all` | Mark everything the current filter shows as read |
| POST | `/posts` | Create |
| GET/PUT/DELETE | `/posts/{id}` | Read, update, delete |

## Media library

`#/media`. Files live on disk in `storage/app/public/media`; folders are database
records, so renaming or deleting a folder never changes a URL that is already published
in a post.

- **Folders** — create, rename and delete, plus the built-in *All files* and *Unfiled*
  views. Deleting a folder keeps its files and moves them to Unfiled; it is not a purge.
- **Upload** — drag files anywhere onto the library, or use the Upload button. Up to 20 at
  once with a real progress bar per batch; anything rejected is reported per file. Files
  land in whichever folder you are viewing.
- **Copy link** — one click on a card copies the public URL. Falls back to a hidden field
  when the Clipboard API is unavailable (it needs a secure context).
- **Edit** — a details panel for file name, alt text, caption and folder, with the type,
  size, uploader and a read-only public link.
- **Used by** — the details panel lists posts using the file, both as a featured image and
  inside a post body, and the delete confirmation warns when a file is in use.
- **Bulk** — tick several files to move them into a folder or delete them together.
- **Picker** — the editor's image button and the featured-image box open the library to
  reuse an existing file rather than uploading another copy.

Accepted: jpg, jpeg, png, gif, webp, avif, pdf, doc(x), xls(x), ppt(x), csv, txt, zip —
10 MB each. **SVG is deliberately excluded**: it is XML that can carry `<script>`, and
these files are served from the same origin as the admin panel, so one crafted upload from
a lower-trust author could read a session. Add it only behind a sanitiser.

Authors are scoped to their own uploads for editing and deleting, the same rule as posts.

## Pages and sections

`#/pages` lists pages; creating one starts from a template (Blank, Landing, About,
Contact) that fills its sections so a new page is editable rather than empty. Page slugs
are stored with a leading slash — `/about` — so they read like the URL they serve and
match how the menu links to them. An empty slug means the site root.

### The two halves

A section is built **once** and used on **many pages**, so it lives in two tables.

| | `section_templates` — the library | `sections` — one page's instance |
| --- | --- | --- |
| Holds | name, HTML, CSS, the field definitions | which template, the values typed into it, the box style |
| Edited at | `#/sections` — **Section library** | the page editor's **Sections** tab |
| Change reaches | every page using it | that page only |

That split is the whole point: fix the markup once and every page follows, while each
page keeps its own words.

Five sections ship in the library: **Breadcrumb**, **Common Hero**, **Card Grid**,
**Rich Text** and **Call To Action**. They are ordinary rows — edit or delete them.

### A page is only its sections

A published page prints **nothing** the builder did not put there. There is no automatic
title band: if a page should show its name, a section has to say so. That is what the
**Breadcrumb** section is for — it draws the trail (`Home › About us`), an optional `<h1>`
and an optional intro, and its last crumb is `{{ page_title }}`, so it follows a rename by
itself. Leave the title or intro blank and they collapse (`:empty { display: none }`)
rather than leaving a gap.

The consequence worth knowing: **a page's `<h1>` now comes from a section.** The Landing
starter gets it from the hero; Blank, About and Contact get it from the breadcrumb. A page
built only from sections that have no `<h1>` will not have one, which search engines care
about.

### Adding a section to a page

The Sections tab offers two buttons, which are the only two kinds there are.

- **Add from library** — pick a section someone already built. Its fields appear as a
  form; what you type is stored on the page, not in the library.
- **Add blank section** — plain **HTML + CSS** written for this page and nowhere else.
  No fields. If it turns out to be worth reusing, rebuild it in the library.

### Fields

A library section defines its own fields. There are five types:

| Type | Renders as |
| --- | --- |
| `text` | one line, HTML-escaped |
| `richtext` | real markup, from a small TipTap editor — inserted unescaped, on purpose |
| `media` | a URL picked from the media library |
| `url` | a URL for an `href` or `src` |
| `group` | a **repeatable** set of the four above |

The key becomes the token. `{{ heading }}` is a value; a group is a block:

```html
<div class="card-grid">
  {{#cards}}
    <a class="card" href="{{ link }}">
      <img src="{{ image }}" alt="{{ title }}">
      <h3>{{ title }}</h3>
    </a>
  {{/cards}}
</div>
```

Everything between `{{#cards}}` and `{{/cards}}` repeats once per item, with that item's
fields in scope. On a page, each item is a card in the editor: add, duplicate, reorder,
remove. Groups nest one level — a group inside a group degrades to a text field, on both
sides of the wire.

A non-group field can carry a **default**, which is what a page starts with when the
section is added to it — so a breadcrumb arrives already saying `Home` → `/` instead of
being retyped on every page.

Values are escaped by type before substitution: everything is HTML-escaped except rich
text. In CSS, values are stripped of `{}<>` rather than HTML-escaped, because `&amp;` is
not a colour.

### Tokens that are not fields

Four tokens come from outside the field list:

| Token | Is |
| --- | --- |
| `{{ page_title }}` | the title of the page being rendered |
| `{{ page_url }}` | its slug, with a leading slash |
| `{{ _number }}` | which item of a group this is, counting from 1 |
| `{{ _index }}` | the same, counting from 0 |

The page ones let a section follow the page it sits on — a breadcrumb's last crumb
survives a rename without anyone editing it. The counters are only meaningful between
`{{#group}}` and `{{/group}}`, and an inner group replaces an outer one's.

A field of the same name always wins, so adding a token can never change what an existing
section renders.

### CSS, and why it cannot leak

A section's CSS is printed in the page's `<head>`, in one `<style id="section-styles">`,
with **every rule rewritten to sit under that section's id**: `.hero { … }` becomes
`#section-12 .hero { … }`. So a rule can only ever style its own section, however
generic its selector. Rules inside `@media`, `@supports`, `@layer`, `@container` and
`@scope` are scoped too; `@font-face` and `@keyframes` are left alone. `:root`, `html`
and `body` are read as "this section".

Tokens work in CSS as well, so a colour field can drive a rule.

### Section style: a band and a column

Every section, of either kind, renders as **two boxes**:

```html
<section class="site-section" style="…the band…">
  <div class="site-section-inner" style="…the column…">
```

The **band** runs the full width of the window; the **column** inside it holds the words.
That is the layout most sites use — a background colour or photo reaching both edges with
the text down the middle — and it is why the max width is a *column* setting. Put it on
the band and it would crop the background with it.

The **Section style** tab is split the same way.

| Band — full width | Column — inside it |
| --- | --- |
| Background colour, **background image** (+ size, position, repeat), text colour, padding, margin, border radius | Content width, text align |
| Anything else — any CSS declarations | Anything else — `display:grid`, `gap`, whatever lays the content out |

Every box is a **text input, not a preset menu** — type any value the CSS property
accepts. The two "anything else" boxes take a block of declarations, one per line, so
`display:flex; align-items:center; min-height:70vh` is fine. They are written onto the
section as an inline style, so they always win over the library CSS — except against a
rule that sets the same property on an *inner* element. A library's
`.lead { color: #605d5d }` still beats the band's text colour, because it targets a child;
change it in the library, or override it from the band's "anything else".

A background image is written as longhand (`background-image` + `background-size` + …)
rather than the `background` shorthand, so a colour set above still shows behind an image
that does not cover. Its url is stripped of quotes, brackets and backslashes before it
goes inside `url("…")` — those are what would let a value close the function early and
start a second declaration.

*Hide on mobile* and *Hide on desktop* cannot be expressed inline, so they become one more
scoped rule in the head.

Because sections set their own width, a page view drops the layout's centred `<main>`
(`@section('main_class', 'w-full')`). Blog views are untouched and keep the 1100px column.

### The preview

Both builders show a live preview in an iframe, running the same substitution, the same
scoping and the same inline style as the server, so what you see is what publishes. The
library previews with stand-in values; a page previews with its own.

The preview builds the same two boxes the page does, so a background wider than its
content looks in the builder exactly as it will on the site.

`app/Support/SectionRenderer.php` owns the server side; `resources/js/admin/lib/sections/`
mirrors it for the preview — `template.js` for tokens and groups, `style.js` for scoping
and the two inline styles, `preview.js` for the frame. **If you change one, change the
other** — they are deliberately two implementations of one rule, and a difference between
them shows up as a preview that lies. `inlineStyleFrom` / `contentStyleFrom` are expected
to produce output byte-identical to `inlineStyle()` / `contentStyle()`.

## Categories

`#/categories` manages the blog's taxonomy. Up to **three levels**, edited the
same way Menu control is — a flat list where each row carries its own depth,
dragged to reorder, `→` to nest. `lib/tree-list.js` is shared by both, and the
depth rules are enforced in the browser *and* again on the server.

**One category per post.** The hierarchy is the grouping; a post picks one
place to live. The post editor's dropdown indents by depth so a sub-category
reads as one.

### What a category carries

Name, address, a parent, a description, an image, and its own `meta_title` /
`meta_description` — a category archive is a public page, so it gets the same
SEO fields every other public page here has, falling back to the name and
description.

### The archive includes the branch

`/category/engineering` lists posts filed in Engineering **and everything
beneath it**, with the sub-categories shown as links and a breadcrumb naming
the ancestors. A parent whose children hold all the writing would otherwise
look empty, which is not what anyone clicking it means.

The admin list shows both numbers: posts filed *directly* in a category, and
posts anywhere in its branch.

### Deleting says what will happen

The dialog counts what is at risk and lets you decide the part that cannot be
guessed:

- **Posts filed directly here** move wherever you choose, or become
  uncategorised. They are never deleted.
- **Sub-categories move up a level** rather than being destroyed — and
  *their* posts stay with them, because those categories still exist.

### Loops are impossible

A category cannot be moved inside itself or inside one of its own
descendants — the branch would detach from the root and every post under it
would vanish from the site with no way back through the UI. The parent
dropdown leaves those options out, and `legalParent()` refuses them again on
the server whatever the request contains.

## Menu control

`#/menu` edits the site's navigation. Two menus ship: **Header** and **Footer**.

### Flat list, not a tree

The menu is stored as parents and children, but it is **edited as a flat list
where each row carries its own depth**. That is the shape a drag-and-drop list
actually has: moving an item is moving one element of an array, and nesting it
is changing one number. `SaveMenu` rebuilds the parents by walking the list
with a stack of the last row seen at each depth.

Depth is **clamped, never trusted**. A row can only ever be one level deeper
than the row above it, and never deeper than `MenuItem::MAX_DEPTH`. That holds
in the browser *and* again on the server, so an impossible tree cannot be
saved however the list arrived — paste a depth of 5 into the request and it
comes back as a legal 2.

Moving a row takes its whole branch with it. Dragging *Services* moves its
sub-items too, which is the only behaviour that is not surprising.

### Three levels

| Level | Renders as |
| --- | --- |
| 1 | the top bar |
| 2 | a dropdown under its parent |
| 3 | a flyout beside the dropdown |

No JavaScript on the public side: sub-menus open on `:hover` **and**
`:focus-within`, so someone tabbing through reaches the deeper levels the same
way a mouse does. Under 860px they stack instead, because a phone has no hover.

The footer prints one row, so anything nested under a footer item is kept but
not shown there.

### Icons

`App\Support\MenuIcons` is one map of ~36 line icons, and it is the **only**
source: the picker, the tree beside it and the public header all draw from it.
An item stores a *name*, never markup — so an icon can be redrawn once and
every menu follows, and nothing a menu stores is ever injected into a page as
raw svg. A name the map no longer knows is dropped on save and simply renders
without an icon.

### Visibility

Public, Authenticated, or Admin only. This is **absence, not hiding**: an
item the viewer may not see never reaches their HTML, so it cannot be found by
reading the source. "Admin only" means anyone who can open the panel, rather
than a named role, so a new role with panel access does not silently lose
items.

There is deliberately **no per-item endpoint**. Reordering and re-nesting
change several rows at once, so saving one row at a time would let a
half-applied move leave the menu in a shape the builder never showed anyone.

## The form builder

`#/forms` lists forms; opening one is the builder. A form is **fields + settings +
whatever came back**, and it reaches the public site through a section, so it can sit on
any page the section builder can.

### Building one

The **Fields** tab is the same three-column shell as the section builder: the list on the
left, the field being edited in the middle, and on the right *what the visitor sees* —
not a drawing of it, but the real `.cms-*` markup from the same stylesheet the published
form uses.

Eleven field types: text, email, phone, link, number, date, long text, dropdown, radio
buttons, checkbox and file upload. Each field has a label, a **name**, a width (full row
or half), an optional placeholder and help line, a required toggle, and a box for extra
rules.

- **The name is the key every submission is stored under.** It follows the label while
  you are typing a new field and stops the moment you edit it by hand, because renaming
  a live field would orphan the answers already collected under the old key. The builder
  says so on the field. Names are snake_cased and de-duplicated, the same way section
  field keys are.
- **A checkbox with options is a multi-choice; one with none is a single yes/no**, the
  shape you want for "I agree to…". The builder switches between them as options are
  added or removed.

### Validation

Whatever the browser does is a convenience. Every submission is validated again on the
server, built from the field definitions rather than from the request:

| The field says | The server checks |
| --- | --- |
| type `email` | a real address, max 255 |
| type `number` / `date` / `url` | numeric / a date / a URL |
| type `file` | a file, max 5 MB |
| dropdown or radio | one of *that field's own options* — a hand-edited value is refused |
| multi-checkbox | an array, each member one of the options |
| required | present, and for a lone checkbox actually ticked |

The **Extra rules** box takes Laravel rules separated by `|` — `min:10`, `max:500`,
`after:today`. Anything that could reach the database or another field is dropped
(`exists`, `unique`, `same`, `confirmed`, `required_if`, …): a validation rule typed into
a form editor is not a safe place to accept arbitrary strings.

Errors come back on the page the visitor was already on, with what they typed still in
the boxes. Each form gets **its own error bag**, so two forms on one page never show each
other's messages, and only the one that actually failed replays its old input.

### Spam, and what is kept

Every form carries a **honeypot** — a field positioned off-screen rather than hidden with
`display:none`, because some bots skip anything a stylesheet has hidden outright.
Anything that fills it in is thanked exactly as a person is, and the submission is
dropped: nothing stored, nothing emailed. The public route is also rate limited to 12
posts a minute.

**Settings** covers the button label, the thank-you message, an address to notify, and
two switches:

- **Status** — an inactive form still appears on the page but says it is closed and
  refuses posts, so a form can be paused without deleting the submissions behind it.
- **Keep submissions** — off, and answers are emailed and then forgotten; nothing is
  written to the database.

A notification that fails to send is logged and swallowed. The submission is already
saved by that point, and a broken mail server must never cost a visitor their message.

### Reading what came back

The **Submissions** tab lists what arrived, newest first, and opens one at a time.
Answers are shown against the form *as it is now*, falling back to the stored key for a
field that has since been renamed or removed — so a record never loses data just because
the form moved on. An uploaded file shows as a link to it. **Export CSV** streams every
submission in chunks, with the field labels as the header.

Submissions can be read and deleted, never edited: a submission is a record of something
that happened.

### The inbox

`#/inbox` is every form's submissions in one list, laid out the way a mail
client is — forms on the left, messages in the middle, the one you are reading
on the right — because that is the shape people already know for this job.

- **Unread is tracked per submission** (`form_submissions.read_at`, null meaning
  unread). Opening one marks it read, exactly as a mail client does, and the
  sidebar badge counts what is still waiting across every form.
- **Filter** by form, by read state, or by free text. The search casts the
  payload to text and matches inside it — coarse, but it is what "find the
  message from Rahman" needs, and the keys differ per form so there is no
  column to search.
- **Bulk**: tick rows to mark read, mark unread or delete. **Mark all read**
  applies to whatever the current filter shows, not blindly to everything.
- Ordering is `created_at` then `id`. The id is not decoration: two
  submissions can arrive in the same second, and without it their order
  changes between requests.

The per-form **Submissions** tab is still there — it answers "what did this
form get". The inbox answers "what has come in", which is the question someone
actually opens the panel to ask.

### Putting a form on a page

A library section can have a field of type **Form**. The value is which form to show, and
the renderer swaps the token for the built markup:

```html
<div class="form-block">
  <h2>{{ heading }}</h2>
  <div class="form-block-form">{{ form }}</div>
</div>
```

The shipped **Contact Form** section does exactly that. In the page editor the field is a
dropdown of active forms; in the preview it draws a named placeholder, because the form's
markup is built on the server and the browser cannot honestly reproduce it.

### Where it lives

| Piece | File |
| --- | --- |
| Rules from field definitions | `app/Support/FormRules.php` |
| The public markup | `app/Support/FormRenderer.php` + `frontend/partials/form*.blade.php` |
| Saving the field list | `app/Actions/Forms/SaveFields.php` |
| Taking a submission | `app/Http/Controllers/Site/FormSubmissionController.php` |
| The builder screen | `resources/js/admin/screens/form-builder.js` |

## The post editor

`New post` opens a full-page editor at `#/posts/new`; editing opens `#/posts/{id}`.
It has two tabs.

**Content** — a TipTap rich-text editor. The toolbar does headings (H2–H4), bold, italic,
underline, strikethrough, inline code, text colour, highlight, bullet and numbered lists,
quotes, code blocks, dividers, line breaks, paragraph spacing, alignment, links, images and
undo/redo. It saves clean HTML into `posts.body`, which is what the public page renders, so
headings stay real headings for search engines.

*Paragraph spacing* — the `↕` control sets the gap between paragraphs for the whole post:
tight, normal or loose. It is stored in `posts.content_spacing` and applied to the public
page, so the writer and the reader see the same rhythm. The `↵` button (or Shift+Enter)
starts a new line inside the same paragraph, with no gap at all.

*Images* upload to `storage/app/public/media` through `POST /admin/api/media`, land in the
`media` table, and prompt for alt text on insert. Selecting an image opens a bar above the
writing area for its size (25 / 50 / 75 / 100%), alignment (left and right float, with text
wrapping), alt text and caption. Images serialise as a `<figure data-post-image>` with a
real `<figcaption>`; plain `<img>` tags from older posts still open fine and default to full
width. On screens under 640px every image goes full width and floats are dropped.

The featured image in the sidebar is separate — it is used on the blog card, at the top of
the post and as the social share image.

**SEO** — meta title, meta description, focus keyword, canonical URL and a noindex toggle,
with a live Google result preview and ten checks scored out of 100: title and description
length, the focus keyword in the title, slug, first paragraph and meta description, word
count, subheadings, image alt text and outgoing links.

The slug is generated server-side on save, but the panel previews it client-side from the
title (`lib/seo.js` mirrors the server's rule), so the slug check is honest before the
first save.

### Posts behaviour

- A blank slug is generated from the title and made unique (`my-post`, `my-post-2`, …).
- Saving as **Published** or **Scheduled** with no date stamps now; moving back to
  **Draft** clears the date.
- Only `Published` posts with a `published_at` in the past appear on the public site,
  so Scheduled posts stay hidden until their date passes.
- `can.update` / `can.delete` on each row drive which buttons the UI shows. The
  **Author** role is scoped to "Own content" and can only edit posts it wrote.
- Creates, updates and deletes are written to `activity_logs` and show on the dashboard.

## Build status

**Done** — the layout shell, login, Dashboard, the full Posts module with a rich-text
editor and SEO panel, the Media library with folders and a picker, Pages with starter
templates, the reusable section library with repeatable groups, the form builder with
server-side validation and an inbox for what comes back, Menu control driving the site's
header and footer, nested Categories, and a public site serving both the pages and the blog with canonical
URLs, Open Graph tags and structured data.

**Still to build** — User management, Roles & permissions, Security, Settings. Their tables, models and permissions already exist; each needs a controller and
a screen. Posts is the template to copy — most of the work is reused from
`resources/js/admin/lib/resource-table.js`.

## Two things to know before extending it

**Merge screen objects with `lib/extend.js`, never object spread.** Spread calls getters
once and stores the result, which silently freezes computed properties like `resultCount`.

**Post content styling lives in `css/content.css`, not in the editor or the public
stylesheet.** Both the writing surface and the public `.post-body` carry the
`.rich-content` class, so spacing, image sizes and alignment cannot drift apart between
what the writer sees and what a reader gets.

**In the editor's templates, read `active.*`, never call `isActive()` or `imageAttr()`.**
`active` is a getter that snapshots the editor state and depends on `revision`. A method
that reads `this.revision` is not reliably tracked as a dependency inside an `x-for` scope
— that left the image bar's size and alignment buttons stuck on their first render even
though the underlying data was correct.

**Keep the TipTap instance out of Alpine's reactive data.** In `screens/post-editor.js`
and in `lib/sections/field-editor.js` it lives in a closure variable, not a property.
Alpine proxies every property it returns, and ProseMirror compares editor state by
identity — a proxied instance throws "Applying a mismatched transaction" on the first
keystroke.

**A form's markup must not depend on `$errors`.** That variable is shared by the `web`
middleware group, so a form rendered from anywhere else — a console command, a queued
job — would blow up on it. `FormRenderer` reads the bag out of the session itself and
hands the view a real `MessageBag`, which is why `php artisan tinker` can render a page
of sections without a request.

**A token used twice renders the same value twice.** That is correct — and it
is also how a section ends up showing one form in two slots: a second slot
gets pasted with the first one's token. The library builder now says so, on
the field and under the HTML, and calls it out loudly for `form` and `media`
fields where it is almost never intended. A field the HTML never mentions is
flagged the same way.

**Never write a section token literally in a Blade file.** Blade would compile
`{{ heading }}` as its own expression. The builder's templates call `tokenFor(key)`,
`groupOpen(key)` and `groupClose(key)` instead, which build the braces in JavaScript.

**`{!! $post->body !!}` renders raw editor HTML on the public post page.** That is normal
CMS behaviour and fine while every author is trusted. If you ever open authoring to
untrusted users, run the body through an HTML sanitiser first.
