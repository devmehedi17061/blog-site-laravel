@extends('frontend.layout')

{{-- The built-in homepage, shown at "/" while no page is chosen as the
     homepage under Content → Pages. --}}
@section('title', $site->homeTitle())
@section('main_class', 'w-full')

@section('content')

  <section class="course-hero">
    <div class="mx-auto grid max-w-[var(--site-width)] items-center gap-10 px-4 py-16 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:py-24">
      <div>
        <div class="kicker mb-3">{{ $site->get('tagline') ?: 'Online courses' }}</div>
        <h1 class="!mb-4 text-[clamp(34px,5.5vw,58px)] leading-[1.05]">
          Learn the skills<br>that move you <span class="text-accent">forward.</span>
        </h1>
        <p class="mb-7 max-w-[540px] text-[17px] leading-relaxed text-neutral-300">
          Practical courses from people who do the work. Learn at your own pace, track your progress, and earn a certificate when you finish.
        </p>

        <form method="GET" action="{{ route('courses.index') }}" class="flex max-w-[520px] gap-2" role="search">
          <input type="search" name="q" class="input !h-[46px] !border-neutral-700 !bg-neutral-900 !text-white" placeholder="What do you want to learn?" aria-label="Search courses">
          <button class="btn btn-primary btn-lg !h-[46px]">Search</button>
        </form>

        <div class="mt-6 flex flex-wrap gap-3">
          <a href="{{ route('courses.index') }}" class="btn btn-primary btn-lg">Browse courses →</a>
          @guest
            @if (\App\Support\Lms::setting('allow_registration'))
              <a href="{{ route('student.register') }}" class="btn btn-lg border border-neutral-600 !text-white hover:border-white">Join for free</a>
            @endif
          @else
            <a href="{{ route('student.dashboard') }}" class="btn btn-lg border border-neutral-600 !text-white hover:border-white">My courses</a>
          @endguest
        </div>
      </div>

      {{-- the numbers, in the panel's stat style --}}
      <div class="grid grid-cols-2 gap-px border border-neutral-700 bg-neutral-700">
        @foreach ([
          ['value' => $stats['courses'], 'label' => 'Courses'],
          ['value' => $stats['students'], 'label' => 'Students'],
          ['value' => $stats['enrollments'], 'label' => 'Enrollments'],
          ['value' => $stats['instructors'], 'label' => 'Instructors'],
        ] as $stat)
          <div class="bg-neutral-950 p-6">
            <div class="font-heading text-[clamp(28px,4vw,40px)] font-extrabold leading-none text-white">{{ number_format($stat['value']) }}</div>
            <div class="mt-2 text-[11px] font-bold tracking-[0.14em] text-accent-300 uppercase">{{ $stat['label'] }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <div class="mx-auto flex max-w-[var(--site-width)] flex-col gap-16 px-4 py-14">

    @if ($topics->isNotEmpty())
      <section>
        <div class="mb-5 flex items-center gap-[10px]">
          <span class="h-3.5 w-3.5 bg-accent"></span>
          <h2 class="!mb-0 text-[24px]">Explore topics</h2>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          @foreach ($topics as $topic)
            <a href="{{ route('courses.index', ['category' => $topic->slug]) }}"
               class="panel group flex items-center gap-3 p-4 no-underline transition hover:border-accent hover:shadow-md">
              <span class="grid h-10 w-10 flex-none place-items-center bg-neutral-900 font-heading text-[15px] font-extrabold text-accent transition group-hover:bg-accent group-hover:text-white">
                {{ mb_strtoupper(mb_substr($topic->name, 0, 1)) }}
              </span>
              <span class="min-w-0">
                <span class="block truncate font-heading text-[14px] font-bold text-ink">{{ $topic->name }}</span>
                <span class="block text-[12px] text-neutral-600">{{ $topic->courses_count }} {{ Str::plural('course', $topic->courses_count) }}</span>
              </span>
            </a>
          @endforeach
        </div>
      </section>
    @endif

    <section>
      <div class="mb-5 flex flex-wrap items-center gap-[10px]">
        <span class="h-3.5 w-3.5 bg-accent"></span>
        <h2 class="!mb-0 text-[24px]">Popular courses</h2>
        <a href="{{ route('courses.index') }}" class="ml-auto font-heading text-[13px] font-bold text-accent no-underline">All courses →</a>
      </div>

      @if ($courses->isEmpty())
        <div class="panel max-w-[560px]">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> No courses yet</div>
          <p class="panel-body text-sm text-neutral-700">
            Build your first one under <strong>LMS → Courses</strong> in the
            <a href="{{ route('admin.panel') }}" class="text-accent">admin panel</a>.
          </p>
        </div>
      @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($courses as $course)
            @include('frontend.partials.course-card', ['course' => $course])
          @endforeach
        </div>
      @endif
    </section>

    {{-- how it works: three steps, numbered like the rest of the system --}}
    <section class="grid gap-px border border-line-strong bg-line-strong md:grid-cols-3">
      @foreach ([
        ['Pick a course', 'Browse by topic or level, watch the free previews, and choose what fits.'],
        ['Learn at your pace', 'Video and reading lessons, files to download, and progress saved as you go.'],
        ['Earn your certificate', 'Finish every lesson and print a certificate with your name on it.'],
      ] as $i => [$title, $text])
        <div class="bg-surface p-7">
          <div class="mb-3 font-heading text-[36px] font-extrabold leading-none text-accent">0{{ $i + 1 }}</div>
          <h3 class="!mb-2 text-[18px]">{{ $title }}</h3>
          <p class="text-[14px] leading-relaxed text-neutral-700">{{ $text }}</p>
        </div>
      @endforeach
    </section>

    @if ($posts->isNotEmpty())
      <section>
        <div class="mb-5 flex flex-wrap items-center gap-[10px]">
          <span class="h-3.5 w-3.5 bg-accent"></span>
          <h2 class="!mb-0 text-[24px]">From the blog</h2>
          <a href="{{ route('blog.index') }}" class="ml-auto font-heading text-[13px] font-bold text-accent no-underline">All posts →</a>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          @foreach ($posts as $post)
            @include('frontend.partials.post-card', ['post' => $post])
          @endforeach
        </div>
      </section>
    @endif
  </div>

@endsection
