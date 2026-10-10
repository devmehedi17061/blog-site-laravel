# LMS চালানোর গাইড

এই সাইটটা এখন একটা **LMS (Learning Management System)**: course বানানো, বিক্রি করা, student-দের পড়ানো, progress দেখা আর certificate দেওয়া। Blog, page builder, menu, form আগের মতোই আছে, একই design-এ।

Install আর live করার নিয়ম: [install-deploy-bn.md](install-deploy-bn.md)।

---

## ১. প্রথমবার চালু করা (XAMPP)

1. XAMPP Control Panel থেকে **MySQL** চালু করুন।
2. `.env`-এ database ঠিক আছে কিনা দেখুন:
   ```
   DB_DATABASE=lms_site_laravel
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Project folder-এ terminal খুলে:
   ```bash
   composer install
   npm install
   npm run build
   php artisan cms:install            # নিজের admin account দিয়ে
   # অথবা, শুধু নিজের কম্পিউটারে demo course সহ:
   php artisan cms:install --demo
   ```
4. চালান:
   ```bash
   php artisan serve --port=8080
   ```
   - সাইট: `http://localhost:8080`
   - Admin: `http://localhost:8080/admin`
   - Student sign in: `http://localhost:8080/login`

> আগের database থেকে update করলে শুধু `php artisan migrate` চালালেই LMS-এর table, permission আর role যোগ হয়ে যাবে। আগের content-এ হাত পড়বে না।

---

## ২. দুই রকম account

| | Staff (admin, editor, instructor) | Student |
| --- | --- | --- |
| কোথায় sign in | `/admin/login` | `/login` |
| Account কে বানায় | Admin → **User management** | Student নিজে `/register` থেকে, অথবা admin **Enroll a student** দিয়ে |
| কী দেখতে পায় | Admin panel (role অনুযায়ী) | শুধু নিজের course, progress, certificate |

Student যদি `/admin`-এ যায়, তাকে নিজের **My courses** page-এ পাঠিয়ে দেওয়া হয়। Admin panel শুধু staff-এর জন্য।

**Instructor** role: নিজের course বানাতে আর edit করতে পারে, নিজের course-এর student-দের দেখতে পারে। অন্যের course ছুঁতে পারে না, course delete-ও করতে পারে না। Instructor বানাতে: User management → user → Role: **Instructor**।

---

## ৩. Course বানানো

Admin → **LMS → Courses → New course**।

### Details tab
- **Title** আর **Subtitle** (title-এর নিচে এক-দুই লাইন)
- **About this course**: বিস্তারিত বর্ণনা (bold, heading, list, link দেওয়া যায়)
- **What students will learn** আর **Requirements**: এক লাইন করে যোগ করুন (Enter চাপলে নতুন লাইন)
- **Intro video**: YouTube বা Vimeo link দিলে course page-এ ছবির জায়গায় video দেখাবে

### Curriculum tab: section আর lesson
Course হলো কয়েকটা **section** (অধ্যায়), প্রতিটায় কয়েকটা **lesson**।

1. **+ Add section**: section-এর নাম লিখুন।
2. **+ Add lesson**: একটা window খুলবে:
   - **Type**: *Video* বা *Article* (লেখা)
   - **Video**: দুইভাবে দেওয়া যায়:
     - **YouTube / Vimeo link**: সাধারণ link (`youtube.com/watch?v=…`), Vimeo link, বা `.mp4` file-এর address। YouTube video **Unlisted** রাখুন। **Private** video অন্য কোনো website-এ চলে না (YouTube নিজেই বন্ধ করে রাখে)।
     - **Upload a private video**: video file সরাসরি upload করুন। File এই সাইটেই private ভাবে থাকে, কোনো public link থাকে না, শুধু enroll করা (Active) student-রা দেখতে পায়। ছোট ছোট টুকরো করে upload হয়, তাই বড় file-ও যায়; upload শেষ না হওয়া পর্যন্ত window খোলা রাখুন। **আগে course একবার Save করে নিন**, তারপর video upload করুন।
   - **Notes / Lesson text**: video-র নিচের লেখা, বা article lesson-এর পুরো লেখা
   - **Length (minutes)**: course page-এ মোট সময় হিসেবে দেখায়
   - **Download for students**: Media library থেকে PDF, slide, zip ইত্যাদি
   - **Free preview**: চালু করলে enroll না করেও যে কেউ এই lesson দেখতে পারবে (বিক্রি বাড়াতে প্রথম ১-২টা lesson preview রাখুন)
3. ↑ ↓ দিয়ে section আর lesson-এর ক্রম বদলান। অন্য section-এ সরাতে lesson খুলে **Section** বদলান।

> Lesson-এর নাম বা ক্রম বদলালে student-দের progress হারায় না। শুধু lesson **মুছে ফেললে** সেই lesson-এর progress যায়।

### Sidebar
- **Publish**: *Draft* থাকলে catalogue-এ দেখায় না (তবে **Preview ↗** দিয়ে আপনি দেখতে পারবেন)। **Featured** দিলে homepage-এ আর catalogue-এ সবার আগে আসে।
- **Pricing**: **Price 0** মানে free course। **Sale price** দিলে আসল দাম কেটে দেখায় আর % off দেখায়।
- **Course image**: 16:9 ছবি (যেমন 1280 × 720) সবচেয়ে ভালো দেখায়।
- **Organise**: Category (blog-এর category-ই ব্যবহার হয়), Level, Language, Instructor।

সব শেষে **Save** বা **Publish** চাপুন। একটা Save-এই details আর curriculum দুটোই save হয়।

---

## ৪. Course বিক্রি আর payment verification

এই সাইটে card gateway নেই। bKash / Nagad / ব্যাংকে টাকা পাঠিয়ে transaction ID দেওয়ার নিয়মে চলে, আর admin প্রতিটা payment যাচাই (verify) করে।

1. প্রথমে **Settings → LMS**-এ:
   - **Currency symbol**: `৳`
   - **Payment methods**: প্রতি লাইনে একটা (bKash, Nagad, Rocket, Bank transfer)
   - **Payment instructions**: কোন নম্বরে টাকা পাঠাবে (নিজের নম্বর লিখুন)
2. Student **Buy this course** চাপলে checkout-এ দেয়:
   - কোন method-এ পাঠিয়েছে, কত টাকা, **কোন নম্বর থেকে** পাঠিয়েছে
   - **Transaction ID**
   - চাইলে payment-এর **screenshot** (JPG/PNG/PDF, private থাকে, শুধু staff আর ঐ student দেখতে পারে)
3. Payment **Pending** থাকে। Dashboard-এ "waiting for verification" দেখায়, sidebar-এ **Payments**-এর পাশে সংখ্যা দেখায়।
4. **LMS → Payments**-এ প্রতিটা payment-এর পাশে সব তথ্য থাকে: screenshot (ক্লিক করলে বড় হয়), কত টাকা দিয়েছে আর course-এর দাম কত, কোন নম্বর থেকে, transaction ID (ক্লিক করলে copy হয়)। নিজের bKash/Nagad/ব্যাংকের হিসাবের সাথে মিলিয়ে:
   - **✓ Verify**: course সাথে সাথে খুলে যায়, student notification আর email পায়, আর একটা **receipt** (INV-00012) তৈরি হয়।
   - **✕ Reject**: কারণ লিখতে হয় (বাছাই করা কারণও আছে)। Student সেই কারণ দেখে আর checkout থেকে আবার পাঠাতে পারে।
5. সাইট নিজেই সতর্ক করে:
   - একই **transaction ID** আগে অন্য কেউ ব্যবহার করলে checkout-এ সেটা গ্রহণ করে না (এক payment দিয়ে দুই course কেনা যায় না)।
   - **কম টাকা** দিলে (course-এর দামের চেয়ে কম) লাল সতর্কতা দেখায়।
   - Screenshot না থাকলে "No screenshot" দেখায়।

**নিজে নিজে খুলে দিতে চাইলে**: Settings → LMS → **Open paid courses straight away** চালু করুন। তখন transaction ID দিলেই course খুলে যায়, কিন্তু payment তবুও Payments-এ verify-এর জন্য থাকে। Reject করলে course আবার বন্ধ হয়ে যায়।

### Enrollments screen
- **Enroll a student**: হাতে হাতে / cash-এ টাকা নিলে। Email দিন; account না থাকলে নাম দিন। নতুন account-এর password **একবারই** দেখাবে। Student একটা notification পায়।
- **Approve** করলে তার pending payment-ও verified হয়; **Cancel** করলে pending payment rejected হয়।
- **Edit** (✎): টাকা, method, transaction ID, staff-only note। **Remove** (🗑): enrollment মুছে ফেলা।

## ৫. Student যা দেখে

| Page | কী আছে |
| --- | --- |
| `/` (homepage) | বড় hero, topic, popular course, "how it works", blog |
| `/courses` | সব course: খোঁজা, topic, level, free/paid, sort |
| `/courses/…` | Course page: কী শিখবে, curriculum, instructor, review, দাম আর **Enroll / Buy** বোতাম |
| `/learn/…` | Course player: video/লেখা, download, ডানে পুরো curriculum আর ✓ চিহ্ন, **Complete & continue** |
| `/my-courses` | **Overview**: যেখানে থেমেছিল সেখান থেকে শুরু, পরিসংখ্যান (চলমান, শেষ, lesson, সময়), 🔥 টানা কত দিন পড়ছে (streak), এই সপ্তাহের chart, payment-এর অবস্থা, notification, recommended course |
| `/my-courses/all` | **My courses**: In progress / Completed / Awaiting payment ট্যাব |
| `/my-payments` | **Payments**: সব payment, অবস্থা (Being verified / Verified / Not verified), reject-এর কারণ, আবার পাঠানো, **receipt** print |
| `/my-certificates` | **Certificates**: সব certificate এক জায়গায় |
| `/notifications` | **Notifications**: payment verify/reject, admin enroll করলে — অপঠিত সংখ্যা sidebar-এ |
| `/my-account` | **Account**: নাম, email, mobile নম্বর (checkout-এ নিজে বসে যায়), password বদলানো |
| `/certificate/…` | সব lesson শেষ হলে certificate। **Print / save as PDF** দিয়ে PDF রাখা যায় |

Student course-এ থাকলে course page-এ ১-৫ star **review** দিতে পারে।

**Password ভুলে গেলে**: `/forgot-password` থেকে email-এ reset link যায়। এর জন্য **Settings → Email** সেট করা লাগবে।

---

## ৬. Settings → LMS-এর অন্য অপশন

| অপশন | কাজ |
| --- | --- |
| Largest video upload (MB) | Upload করা lesson video সর্বোচ্চ কত বড় হতে পারবে (default 2048 MB)। Video **MP4 (H.264), 720p** করে export করলে সব browser-এ চলে আর file ছোট থাকে |
| Lessons in order | চালু থাকলে (default) আগের lesson **Complete** না করলে পরের lesson খোলে না। Video link সরাসরি খুললেও খোলে না। Free preview আর staff-দের জন্য এই নিয়ম নেই |
| Anyone can create a student account | বন্ধ করলে `/register` বন্ধ হয়, শুধু admin student যোগ করতে পারে |
| Course reviews | Star review চালু/বন্ধ |
| Certificates | Certificate চালু/বন্ধ |
| Certificate wording | নাম আর course-এর মাঝের লেখা ("has successfully completed the course") |
| Signed by | Certificate-এ কার নাম। খালি রাখলে course-এর instructor-এর নাম |

---

## ৭. Menu আর homepage

- **Menu control**-এ menu item-এর **Visibility**:
  - *Public*: সবাই দেখে
  - *Authenticated*: sign in করা থাকলে (যেমন "My courses")
  - *Guests*: sign in না থাকলে (যেমন "Sign in", "Create an account")
  - *Admin only*: শুধু staff
- **Homepage**: Pages screen-এ কোনো page homepage না করলে `/`-এ built-in **course homepage** দেখায়। Page builder দিয়ে নিজের homepage বানাতে চাইলে Pages → সেই page → *Set as homepage*।

---

## ৮. Dashboard আর Trash

- **Dashboard**-এ *Live courses* আর *Enrollments · 30 days* দেখায়, আর "Needs attention"-এ approve বাকি থাকা payment আর lesson ছাড়া live course-এর কথা বলে।
- Course delete করলে **Trash**-এ যায়। ৩০ দিনের মধ্যে restore করলে সব student-এর enrollment আর progress ফিরে আসে।

---

## ৯. Coupon (discount code)

Admin → **LMS → Coupons → New coupon**।

| ঘর | মানে |
| --- | --- |
| **Code** | Student যা লিখবে, যেমন `EID50`। **Generate** চাপলে নিজে একটা বানিয়ে দেয়। ছোট/বড় হাতের অক্ষর মিলাতে হয় না |
| **Discount** | **Percent %** (যেমন ৫০% ছাড়) অথবা **Fixed ৳** (যেমন ৫০০ টাকা ছাড়)। দাম কখনো শূন্যের নিচে যায় না |
| **Only for courses of at least** | এর কম দামের course-এ coupon চলবে না (খালি = সব দামে) |
| **Total uses** | মোট কতবার ব্যবহার করা যাবে (খালি = সীমা নেই) |
| **Uses per student** | একজন student কতবার পারবে (সাধারণত ১; ০ = সীমা নেই) |
| **Starts / Ends** | কবে থেকে কবে পর্যন্ত চলবে (খালি = এখনই, মেয়াদ নেই) |
| **Works on** | নির্দিষ্ট course বাছাই করুন; কিছু না বাছলে **সব course-এ** চলবে |
| **Active** | বন্ধ করলে coupon থেমে যায়, মুছে যায় না |

**Student যেভাবে ব্যবহার করে:**
- Checkout-এর ডানে **Have a coupon?** ঘরে code লিখে **Apply**। দাম সাথে সাথে কমে যায়; ভুল হলে কারণ দেখায় (মেয়াদ শেষ, এই course-এর জন্য না, শেষ হয়ে গেছে…)।
- **Promo link**: Coupons তালিকায় 🔗 চাপলে link copy হয়, যেমন `yoursite.com/courses?coupon=EID50`। Facebook-এ দিন; যে ক্লিক করবে তার জন্য coupon checkout-এ নিজে বসে যাবে। একটা course-এর link-এ `?coupon=EID50` দিলেও কাজ করে।
- **১০০% coupon** (যেমন scholarship): checkout-এ payment লাগে না; **Get the course for free** চাপলেই course খুলে যায়।

**হিসাব ঠিক থাকে:**
- ব্যবহার গোনা হয় আসল ব্যবহার দিয়ে। Payment **Reject** করলে সেই ব্যবহার ফেরত আসে।
- একই payment আবার পাঠালে coupon দুইবার গোনা হয় না।
- Payments screen-এ প্রতিটা payment-এ কোন coupon, কত ছাড় তা থাকে; Coupons তালিকায় দেখায় কতবার ব্যবহার হয়েছে আর মোট কত টাকা ছাড় দেওয়া হয়েছে।

> Coupon বানাতে পারে Super Admin আর Editor। Instructor পারে না।

---

## ১০. Quiz

Course → **Curriculum** → lesson খুলুন (বা **+ Add lesson**) → Type: **Quiz**।

- **Pass mark (%)**: কত পেলে পাস (যেমন ৬০)।
- **Tries allowed**: কতবার দেওয়া যাবে (০ = যতবার খুশি)।
- **Shuffle**: প্রতিবার প্রশ্নের ক্রম বদলায়।
- প্রশ্ন তিন রকম: **One right answer**, **Several right answers** (সবগুলো ঠিক বাছলে তবেই সঠিক), **True / false**।
- ✓ বোতাম দিয়ে সঠিক উত্তর চিহ্নিত করুন। প্রতিটা প্রশ্নের **Points** আলাদা দেওয়া যায়, আর **Why** ঘরে ব্যাখ্যা লেখা যায়।

**Student-এর দিক থেকে:**
- সব প্রশ্নের উত্তর দিয়ে **Submit answers**। নম্বর server-এ হিসাব হয়, browser-এ না, তাই ফাঁকি দেওয়া যায় না।
- **পাস করলেই** lesson complete হয় আর পরের lesson খোলে ("Lessons in order" চালু থাকলে)। Quiz-এ "Complete" বোতাম নেই।
- ফেল করলে কোন প্রশ্ন ভুল তা দেখে, কিন্তু সঠিক উত্তর তখনই দেখায় যখন পাস করে **অথবা** আর কোনো try বাকি থাকে না, যাতে আন্দাজে পাস করা না যায়।
- Admin বা Instructor নিজে quiz দিয়ে দেখতে পারে; নম্বর দেখায় কিন্তু save হয় না।

---

## ১১. Q&A (lesson-এর নিচে প্রশ্ন-উত্তর)

- Lesson page-এ **Q&A** tab: course-এ enroll করা student প্রশ্ন করে, অন্যরা reply দিতে পারে।
- Instructor/Admin-এর reply-তে **Instructor** লেখা থাকে, আর প্রশ্নটা **Answered** হয়ে যায়।
- প্রশ্নকারী reply পেলেই notification (আর email) পায়।
- Admin → **LMS → Q&A**: সব course-এর প্রশ্ন এক জায়গায়, **Open** (উত্তর বাকি) আগে। ওখান থেকেই reply দিন। Sidebar আর Dashboard-এ কতগুলো উত্তর বাকি তা দেখায়। Instructor শুধু নিজের course-এর প্রশ্ন দেখে।

---

## ১২. Announcement (course-এর সবাইকে খবর)

Course editor → **Announcements** tab → শিরোনাম আর লেখা → **Send to students**।

- Course-এর সব active student notification পায়।
- Enroll করা student course page-এর উপরে announcement দেখে।
- একসাথে অনেককে email পাঠালে সাইট ধীর হয়ে যায়, তাই announcement শুধু student panel-এর notification-এ যায়, email-এ না।

---

## ১৩. Wishlist আর certificate যাচাই

- **Wishlist**: course page-এ দামের পাশে ♥ চাপলে course **Student panel → Wishlist**-এ জমা থাকে, পরে কেনার জন্য।
- **Certificate যাচাই**: প্রতিটা certificate-এ একটা নম্বর থাকে (যেমন `C-00012-261008-7KQ2PA`) আর "Verify at …/certificates/verify" লেখা থাকে। যে কেউ (যেমন চাকরিদাতা) ঐ page-এ নম্বর দিয়ে দেখতে পারে certificate আসল কিনা, কার নামে, কোন course। নম্বরের শেষ অংশ গোপন key দিয়ে তৈরি, তাই আন্দাজে অন্যের নম্বর বানানো যায় না।

---

## ১৪. শেখা সহজ করার সুবিধা (student-এর দিক থেকে)

- **Video নিজে থেকেই complete হয়**: ৯০% দেখা হলে lesson-টা নিজে থেকেই সম্পন্ন হয়ে যায়, আলাদা করে বাটন চাপতে হয় না।
- **যেখানে থেমেছিলেন সেখান থেকে**: video আবার খুললে আগের জায়গা থেকে চলে। Dashboard-এ "যেখানে থেমেছিলেন সেখান থেকে" কার্ডে এক ক্লিকে শেষ lesson-এ যাওয়া যায়।
- **এরপর (Up next)**: video শেষ হলে পরের lesson-এর নাম দেখায় আর কয়েক সেকেন্ড পরে নিজে থেকেই চালু হয়। "এখানেই থাকুন" চাপলে থামে।
- **মোবাইলে**: নিচে একটা বার থাকে, যাতে আগের / সম্পন্ন / পরের বাটন আছে।
- **Keyboard**: `N` পরের, `P` আগের, `M` সম্পন্ন, `F` ফোকাস মোড (পাশের তালিকা লুকায়)।
- **লেসন খোঁজা আর নোট**: পাশের তালিকায় lesson খোঁজা যায়। "আমার নোট" tab-এ লেখা নোট ঐ browser-এ সেভ থাকে।
- **শুরু করা যাক**: নতুন student-এর dashboard-এ ৫ ধাপের একটা checklist থাকে (মোবাইল নম্বর, প্রথম course, প্রথম lesson, সার্টিফিকেট)। সব শেষ হলে বা × চাপলে চলে যায়।
- **এই সপ্তাহ আর দিনের ধারা**: গত ৭ দিনে কত lesson শেষ হয়েছে তার chart, আর টানা কত দিন শিখেছেন।

---

## ১৫. Admin-এর সময় বাঁচানোর টুল

- **একসাথে অনেক payment verify**: Payments-এ বাম পাশের checkbox দিয়ে কয়েকটা বেছে উপরের **Verify selected** চাপুন।
- **CSV download**: Payments আর Students screen-এ **CSV** বাটন আছে। যে filter দেওয়া আছে সেই তালিকাটাই Excel-এ খোলার মতো file হয়ে নামে (বাংলা নামও ঠিকঠাক দেখায়)।
- **Course কপি**: Courses তালিকায় **Duplicate** চাপলে পুরো course (section, lesson, quiz সহ) একটা Draft কপি হয়। Curriculum-এ একটা lesson-ও কপি করা যায়।
- **টেনে সাজানো**: Curriculum-এ section আর lesson-এর বাম পাশের ⋮⋮ ধরে টেনে ক্রম বদলান, তারপর Save।
- **LMS → Reports**: মাসভিত্তিক আয় আর নতুন ভর্তি, প্রতিটা course-এ কতজন শেষ করেছে আর রেটিং, কোন payment মাধ্যম বেশি চলে, কোন coupon কতবার ব্যবহার হয়েছে, আর কোন quiz প্রশ্নে সবচেয়ে বেশি ভুল হয়। প্রতিটা chart-এর "Show as table" দিয়ে সংখ্যাগুলোও দেখা যায়।
- **Dashboard-এর কাজের তালিকা**: verify বাকি payment আর উত্তর বাকি প্রশ্ন Dashboard-এর উপরেই দেখায়।

---

## ১৬. বাংলা / English

- সাইটের student-দের দিক (course, checkout, player, student panel, blog, ফর্ম, header-footer) **বাংলা আর English** দুই ভাষাতেই চলে। Admin panel English-এ থাকে।
- **কোন ভাষা দেখাবে**: header-এ **EN | বাংলা** switch আছে। Student নিজে যেটা বাছেন সেটা তার account-এ মনে থাকে। কেউ না বাছলে **Settings → Site → Language**-এ যেটা দেওয়া আছে সেটা দেখায়।
- নোটিফিকেশন আর ইমেইলও student-এর বাছা ভাষায় যায়।
- **Header-এ switch না থাকলে**: Builders → Header & footer-এ header-এর HTML-এ `{{ language }}` বসান। না বসালে কোণায় একটা ছোট switch নিজে থেকেই দেখায়।
- **যা অনুবাদ হয় না**: আপনি নিজে যা লেখেন (course-এর নাম ও বিবরণ, lesson, post, page) সেগুলো যেমন লিখেছেন তেমনই দেখায়। বাংলায় চাইলে বাংলায় লিখুন।
- **অনুবাদ বদলাতে**: `lang/bn.json` file-এ বাম পাশে English, ডান পাশে বাংলা। ডান পাশের লেখা বদলে save করলেই হয়, build লাগে না।

---

## ১৭. সমস্যা হলে

| সমস্যা | সমাধান |
| --- | --- |
| Student বলছে course খুলছে না | Enrollments-এ তার status **Pending** কিনা দেখুন, Approve করুন |
| Course catalogue-এ দেখাচ্ছে না | Course **Draft** কিনা দেখুন, Publish করুন |
| Video দেখাচ্ছে না | Lesson-এ link-এর নিচে "will be embedded" লেখা আসছে কিনা দেখুন। YouTube video **Private** হলে চলবে না, **Unlisted** দিন, অথবা video সরাসরি upload করুন |
| Upload করা video চলছে না | MP4 (H.264) file দিন; কিছু MOV/HEVC file কিছু browser-এ চলে না |
| সাইট অন্য hosting-এ নিচ্ছি | Database-এর সাথে `storage/app/private/lessons` folder-ও copy করুন। Upload করা video ওখানে থাকে, Git-এ যায় না |
| Student `/admin`-এ ঢুকতে পারছে না | এটাই নিয়ম, student-রা `/login` ব্যবহার করবে |
| দাম ভুল চিহ্নে দেখাচ্ছে | Settings → LMS → Currency symbol |
| Reset password email যাচ্ছে না | Settings → Email সেট করুন |
| Coupon কাজ করছে না | Coupons-এ status দেখুন (Active / Expired / Used up / Off), আর "Works on"-এ ঐ course আছে কিনা |
| Student পরের lesson-এ যেতে পারছে না | আগের lesson quiz হলে পাস করতে হবে; try শেষ হয়ে গেলে quiz-এর Tries allowed বাড়ান |
| CSS/design ভেঙে গেছে | `npm run build` |
| কিছু লেখা বাংলা হচ্ছে না | ওটা কি আপনার লেখা content (course/post-এর নাম)? তাহলে বাংলায় লিখুন। নাহলে `lang/bn.json`-এ লাইনটা যোগ করুন |
| Design বদলালাম, browser-এ পুরনোটা দেখাচ্ছে | `npm run build` চালান; file-এর ঠিকানার শেষে `?v=` বদলে যায়, তাই browser নতুনটা নেয় |
| সব ঠিক আছে কিনা দেখতে | `php artisan test`: ৮০টা test চলে, সব **passed** হওয়া উচিত |
