# ইনস্টল, Git আর Live করার গাইড

এই সাইটটা একটা **template** হিসেবে Git-এ রাখা যায়। যেকোনো জায়গায় clone করে কয়েকটা command চালালেই নতুন একটা সাইট তৈরি হয়ে যাবে।

---

## ১. Git-এ কী থাকে, কী থাকে না

| Git-এ **থাকে** (template) | Git-এ **থাকে না** (প্রতিটা সাইটের নিজের) |
| --- | --- |
| সব code, design, section library, header/footer design | `.env` (database password, app key) |
| Migration আর seeder (খালি সাইট বানানোর নিয়ম) | `vendor/`, `node_modules/` (install করলে আসে) |
| `public/images/sections/` (slider-এর ছবি) | `public/build/` (`npm run build` বানায়) |
| এই docs | Upload করা ছবি (`storage/app/public/`) |
| | Database-এর content (page, post, user, message) |
| | `recovery-codes.txt` |

> **সতর্কতা:** GitHub repo যদি **public** হয়, তাহলে সবাই code দেখতে পারবে। তাই `.env`, database export বা recovery code কখনো commit করবেন না। এগুলো `.gitignore`-এ দেওয়া আছে। Repo private রাখাই ভালো: GitHub → Settings → Danger Zone → Change visibility।

---

## ২. নতুন কম্পিউটারে clone করে চালানো (XAMPP)

দরকার: PHP 8.2+, Composer, Node.js 20+, MySQL (XAMPP)।

```bash
git clone https://github.com/<আপনার-নাম>/lms-site-laravel.git
cd lms-site-laravel
```

1. phpMyAdmin-এ একটা **খালি database** বানান, যেমন `lms_site_laravel` (utf8mb4_unicode_ci)।
2. `.env.example` কপি করে `.env` নাম দিন। তারপর এই লাইনগুলো ঠিক করুন:
   ```
   APP_URL=http://localhost:8080
   DB_DATABASE=lms_site_laravel
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. একটা command-এই সব হয়ে যাবে:
   ```bash
   composer setup
   ```
   এটা composer install, npm install আর npm run build চালায়, তারপর `php artisan cms:install` চালায়। Install শেষে তিনটা প্রশ্ন করবে:
   - **Site name:** সাইটের নাম
   - **Admin email / password:** আপনার নিজের Super Admin account। Password খালি রাখলে একটা শক্ত password বানিয়ে **একবারই** দেখাবে, লিখে রাখবেন।
4. চালু করুন:
   ```bash
   php artisan serve --port=8080
   ```
   তারপর ব্রাউজারে `http://localhost:8080/admin` খুলুন।

### নতুন সাইটে যা যা আগে থেকে থাকে
- Section library: Hero Slider, Testimonial Slider, Call to Action সহ ৯টা section
- ৩টা header design আর ৩টা footer design (Header & footer builder-এ)
- Contact form
- Menu: Home, Courses, Blog, My courses (student sign-in করা থাকলে), Sign in (sign-in না থাকলে)
- User role আর permission — Instructor আর Student role সহ
- LMS: course catalogue (`/courses`), student sign-up/sign-in (`/register`, `/login`), student area (`/my-courses`), certificate
- Homepage: কোনো page homepage হিসেবে বাছাই না করলে `/`-এ built-in course homepage দেখায়

### Demo সহ চালাতে চাইলে (শুধু নিজের কম্পিউটারে)
```bash
php artisan cms:install --demo
```
এতে sample page, post আর ৫টা sample course (lesson সহ) যোগ হয়, আর demo account তৈরি হয়:

| Email | কোথায় sign in | Role |
| --- | --- | --- |
| arif@example.com | `/admin` | Super Admin |
| mira@example.com | `/admin` | Editor |
| rafiq@example.com | `/admin` | Instructor — শুধু নিজের course |
| student@example.com | `/login` | Student (কিছু course-এ progress আছে) |
| karim@example.com | `/login` | Student |
 সবগুলোর password `password`, তাই live server-এ এই command চলবে না।

---

## ৩. সাইট Live (publish) করা

### ক) cPanel shared hosting (SSH ছাড়া)

**নিজের কম্পিউটারে:**
```bash
composer install --no-dev --optimize-autoloader
npm run build
```
এরপর পুরো folder zip করুন। `node_modules`, `.env` আর `.git` বাদ দেবেন। `vendor` আর `public/build` **অবশ্যই রাখবেন**।

**cPanel-এ:**
1. **File Manager:** zip-টা `/home/<user>/mysite/`-এ upload করে extract করুন। `public_html`-এর **ভেতরে** রাখবেন না।
2. **Domains:** domain-এর Document Root দিন `/home/<user>/mysite/public`। এটা না করা গেলে hosting support-কে বলুন।
3. **MySQL Databases:** database আর user বানান, user-কে সব permission দিন।
4. `.env.example` কপি করে `.env` বানান। তারপর এগুলো দিন:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   DB_DATABASE=cpaneluser_site
   DB_USERNAME=cpaneluser_dbuser
   DB_PASSWORD=আপনার-password
   SESSION_SECURE_COOKIE=true
   ```
5. **Terminal** (cPanel-এ সাধারণত থাকে) খুলে চালান:
   ```bash
   cd ~/mysite
   php artisan cms:install
   php artisan optimize
   ```
   Terminal না থাকলে: নিজের কম্পিউটারে install করে phpMyAdmin → Export দিয়ে `.sql` বানান। তারপর hosting-এর phpMyAdmin → Import করুন (নিচে ৪ নম্বর অংশ দেখুন)।
6. **Cron Jobs:** প্রতি মিনিটের জন্য একটা cron যোগ করুন:
   ```
   * * * * * cd /home/<user>/mysite && php artisan schedule:run >> /dev/null 2>&1
   ```
   এটা scheduled post-এর status ঠিক রাখে আর ৩০ দিনের পুরোনো trash মুছে দেয়।
7. **SSL:** cPanel → SSL/TLS Status থেকে free SSL চালু করুন।

### খ) VPS (SSH, Git আর Node আছে এমন)

```bash
git clone https://github.com/<আপনার-নাম>/lms-site-laravel.git mysite
cd mysite
cp .env.example .env        # তারপর উপরের মতো production value দিন
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan cms:install
php artisan optimize
```
- Nginx বা Apache-এর root দিন `mysite/public`-এ।
- উপরের মতো cron যোগ করুন।
- `storage/` আর `bootstrap/cache/` folder-এ web server-কে লেখার permission দিন।

---

## ৪. এখনকার সাইটের content নতুন জায়গায় নেওয়া

Template (code) আর আপনার content আলাদা জিনিস। এখনকার page, post, ছবি আর setting সহ সাইট সরাতে চাইলে এগুলো লাগবে:

1. **Database:** XAMPP phpMyAdmin → `lms_site_laravel` → Export → SQL। তারপর নতুন জায়গার phpMyAdmin → Import। এক্ষেত্রে `cms:install` চালাবেন না, content তো আগেই আছে। শুধু `php artisan migrate --force` চালান।
2. **Upload করা ফাইল:** `storage/app/public/` folder (media আর form-uploads) কপি করুন। তারপর `php artisan storage:link` চালান।
3. **APP_KEY:** পুরোনো `.env`-এর `APP_KEY` নতুন `.env`-এ দিন। না দিলে SMTP password-এর মতো লুকানো setting পড়া যাবে না, আবার দিতে হবে।
4. Setting-এ গিয়ে site URL আর email setting একবার দেখে নিন।

> Database export-এ user, password hash আর form-এর message থাকে। তাই এটা **কখনো Git-এ দেবেন না**।

---

## ৫. পরে update নেওয়া

Template-এ নতুন feature যোগ হলে live সাইটে:
```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```
cPanel-এ SSH না থাকলে নিজের কম্পিউটারে build করে বদলানো file-গুলো upload করুন, তারপর Terminal থেকে `php artisan migrate --force` চালান।

কোনো update-এর আগে সবসময় database export করে backup রাখবেন।

---

## ৬. সমস্যা হলে

| সমস্যা | সমাধান |
| --- | --- |
| "Cannot reach the database" | MySQL চালু আছে কিনা, আর `.env`-এর DB_ লাইনগুলো দেখুন |
| সাইট design ছাড়া (CSS নেই) | `npm run build` চালানো হয়নি, অথবা `public/build` upload হয়নি |
| ছবি দেখায় না | `php artisan storage:link` চালান |
| 500 error | `storage/logs/laravel.log` দেখুন; `storage` folder-এ লেখার permission দিন |
| Admin-এ ঢুকতে পারছি না (IP rule বা HTTPS lock) | `php artisan security:unlock` |
| 2FA-র ফোন হারিয়ে গেছে | `php artisan security:reset-2fa you@example.com` |
| নতুন admin দরকার | `php artisan cms:install --email=new@you.com` (অন্য কিছু বদলায় না) |
| Student `/admin`-এ ঢুকতে পারছে না | এটাই নিয়ম — student `/login` দিয়ে ঢোকে, admin panel শুধু staff-এর জন্য |
| Student password reset-এর email পাচ্ছে না | Settings → Email-এ mail সেট করুন (default "Log only" কোনো email পাঠায় না) |
| LMS কীভাবে চালাবো | [lms-guide-bn.md](lms-guide-bn.md) দেখুন |
