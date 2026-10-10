<template x-if="$store.ui.screen === 'reports'">
<div x-data="reports" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">LMS</div>
      <h2>Reports</h2>
      <div class="page-subtitle">Money, sign-ups, how far students get, and what they find hard — the last twelve months</div>
    </div>
    <div class="flex flex-wrap gap-2">
      <button class="btn btn-secondary" @click="download('payments/export')" x-show="$store.auth.can('enrollments.view')">⬇ Payments CSV</button>
      <button class="btn btn-secondary" @click="download('students/export')" x-show="$store.auth.can('enrollments.view')">⬇ Students CSV</button>
    </div>
  </div>

  <template x-if="error"><div class="alert mb-4" x-text="error"></div></template>
  <div x-show="loading" class="panel table-empty">Working out the numbers…</div>

  <template x-if="data">
    <div class="flex flex-col gap-6">

      {{-- the headline numbers --}}
      <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="stat-tile bg-neutral-900 text-white"><div class="stat-label !text-accent-300">Revenue, all time</div><div class="stat-value" x-text="data.totals.revenue"></div></div>
        <div class="stat-tile bg-surface">
          <div class="stat-label">This month</div><div class="stat-value" x-text="data.totals.revenue_month"></div>
          <div class="text-[12px] font-semibold" x-show="data.totals.revenue_change !== null"
               :class="data.totals.revenue_change >= 0 ? 'text-success-800' : 'text-accent-700'"
               x-text="`${data.totals.revenue_change >= 0 ? '▲' : '▼'} ${Math.abs(data.totals.revenue_change)}% on last month`"></div>
        </div>
        <div class="stat-tile bg-surface"><div class="stat-label">Students</div><div class="stat-value" x-text="data.totals.students"></div></div>
        <div class="stat-tile bg-surface"><div class="stat-label">Enrollments this month</div><div class="stat-value" x-text="data.totals.enrollments_month"></div></div>
        <div class="stat-tile bg-surface"><div class="stat-label">Finish their course</div><div class="stat-value" x-text="`${data.totals.completion}%`"></div></div>
        <div class="stat-tile bg-surface"><div class="stat-label">Coupon discounts</div><div class="stat-value" x-text="data.totals.discounts"></div></div>
      </div>

      {{-- two charts, two scales: never one chart with two axes --}}
      <div class="grid gap-6 xl:grid-cols-2">
        @foreach (['revenue' => ['Revenue by month', 'revenue_label', 'Verified payments, by the month they were sent'], 'enrollments' => ['New enrollments by month', 'enrollments', 'Every sign-up, free and paid']] as $key => [$title, $valueKey, $note])
          <section class="panel">
            <div class="panel-head">
              <span class="h-2.5 w-2.5 bg-accent"></span> {{ $title }}
              <button type="button" class="ml-auto text-[11px] font-semibold normal-case tracking-normal text-neutral-300 hover:text-white"
                      @click="asTable.{{ $key }} = !asTable.{{ $key }}" x-text="asTable.{{ $key }} ? 'Show chart' : 'Show as table'"></button>
            </div>
            <div class="p-5">
              <p class="mb-4 text-[12px] text-neutral-600">{{ $note }}</p>

              <div class="rp-chart" data-chart="{{ $key }}" x-show="!asTable.{{ $key }}" @mouseleave="hide()" role="img" aria-label="{{ $title }}">
                <div class="rp-grid"><span></span><span></span></div>
                <template x-for="(month, i) in data.months" :key="month.key">
                  <div class="rp-col" @mouseenter="show($event, `${month.long}: ${month.{{ $valueKey }}}`)" @focus="show($event, `${month.long}: ${month.{{ $valueKey }}}`)" tabindex="0">
                    <span class="rp-value" x-show="labelled(month, i, '{{ $key }}')" x-text="month.{{ $valueKey }}"></span>
                    <span class="rp-bar" :style="`height:${height(month, '{{ $key }}')}%`"></span>
                    <span class="rp-label" x-text="month.label"></span>
                  </div>
                </template>
                <div class="rp-tip" x-show="tip && tip.chart === '{{ $key }}'" :style="tip ? `left:${tip.x}px` : ''" x-text="tip?.text"></div>
              </div>

              <table class="data-table" x-show="asTable.{{ $key }}">
                <thead><tr><th>Month</th><th class="text-right">{{ $key === 'revenue' ? 'Revenue' : 'Enrollments' }}</th></tr></thead>
                <tbody>
                  <template x-for="month in data.months" :key="month.key">
                    <tr><td x-text="month.long"></td><td class="text-right font-semibold" x-text="month.{{ $valueKey }}"></td></tr>
                  </template>
                </tbody>
              </table>
            </div>
          </section>
        @endforeach
      </div>

      {{-- each course --}}
      <section class="panel">
        <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Courses</div>
        <div class="overflow-x-auto">
          <table class="data-table min-w-[760px]">
            <thead>
              <tr><th>Course</th><th class="text-right">Students</th><th class="text-right">Revenue</th><th>Finished the course</th><th>Lessons done</th><th>Rating</th></tr>
            </thead>
            <tbody>
              <template x-for="course in data.courses" :key="course.id">
                <tr>
                  <td>
                    <button type="button" class="cell-strong text-left hover:text-accent" @click="$store.ui.go('courses', course.id)" x-text="course.title"></button>
                    <span class="tag tag-neutral ml-1" x-show="course.status !== 'Published'" x-text="course.status"></span>
                  </td>
                  <td class="text-right" x-text="course.students"></td>
                  <td class="text-right font-semibold" x-text="course.revenue_label"></td>
                  <td class="w-[180px]">
                    <div class="flex items-center gap-2">
                      <div class="h-1.5 flex-1 bg-neutral-200"><div class="h-full bg-accent" :style="`width:${course.completion}%`"></div></div>
                      <span class="w-9 text-right text-[12px]" x-text="`${course.completion}%`"></span>
                    </div>
                  </td>
                  <td class="w-[180px]">
                    <div class="flex items-center gap-2">
                      <div class="h-1.5 flex-1 bg-neutral-200"><div class="h-full bg-neutral-700" :style="`width:${course.progress}%`"></div></div>
                      <span class="w-9 text-right text-[12px]" x-text="`${course.progress}%`"></span>
                    </div>
                  </td>
                  <td x-text="course.rating ? `★ ${course.rating} (${course.reviews})` : '—'"></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <div class="table-empty" x-show="!data.courses.length">No courses yet.</div>
      </section>

      <div class="grid gap-6 xl:grid-cols-2">
        {{-- quizzes: what students find hard --}}
        <section class="panel">
          <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Hardest quiz questions</div>
          <div class="p-5">
            <p class="mb-4 text-[12px] text-neutral-600">Share of answers that were wrong. A high one usually means the lesson before it needs another look.</p>
            <template x-for="q in data.quizzes.hardest" :key="q.question">
              <div class="mb-3">
                <div class="mb-1 flex gap-3 text-[13px]">
                  <span class="min-w-0 flex-1 truncate" :title="q.question" x-text="q.question"></span>
                  <strong class="text-ink" x-text="`${q.wrong}% wrong`"></strong>
                </div>
                <div class="h-2 bg-neutral-200"><div class="h-full bg-accent" :style="`width:${Math.max(2, q.wrong)}%`"></div></div>
                <div class="mt-0.5 text-[11px] text-neutral-500" x-text="`${q.quiz} · ${q.answered} answers`"></div>
              </div>
            </template>
            <div class="text-[13px] text-neutral-600" x-show="!data.quizzes.hardest.length">No quiz has been taken yet.</div>

            <table class="data-table mt-4" x-show="data.quizzes.summary.length">
              <thead><tr><th>Quiz</th><th class="text-right">Tries</th><th class="text-right">Pass rate</th><th class="text-right">Average</th></tr></thead>
              <tbody>
                <template x-for="quiz in data.quizzes.summary" :key="quiz.title">
                  <tr>
                    <td><div class="cell-strong" x-text="quiz.title"></div><div class="text-[11px] text-neutral-500" x-text="quiz.course"></div></td>
                    <td class="text-right" x-text="quiz.attempts"></td>
                    <td class="text-right" x-text="quiz.pass_rate === null ? '—' : `${quiz.pass_rate}%`"></td>
                    <td class="text-right" x-text="quiz.average === null ? '—' : `${quiz.average}% (pass ${quiz.pass_mark}%)`"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </section>

        <div class="flex flex-col gap-6">
          <section class="panel">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Payment methods</div>
            <div class="p-5">
              <template x-for="m in data.methods" :key="m.method">
                <div class="mb-3">
                  <div class="mb-1 flex text-[13px]"><span class="flex-1" x-text="m.method"></span><strong x-text="`${m.label} · ${m.count}`"></strong></div>
                  <div class="h-2 bg-neutral-200"><div class="h-full bg-accent" :style="`width:${widthOf(m.amount, data.methods, 'amount')}`"></div></div>
                </div>
              </template>
              <div class="text-[13px] text-neutral-600" x-show="!data.methods.length">No verified payments yet.</div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head"><span class="h-2.5 w-2.5 bg-accent"></span> Coupons used</div>
            <table class="data-table" x-show="data.coupons.length">
              <thead><tr><th>Code</th><th class="text-right">Uses</th><th class="text-right">Discount given</th><th class="text-right">Paid with it</th></tr></thead>
              <tbody>
                <template x-for="c in data.coupons" :key="c.code">
                  <tr>
                    <td><span class="coupon-chip" x-text="c.code"></span> <span class="text-[11px] text-neutral-500" x-text="c.label"></span></td>
                    <td class="text-right" x-text="c.uses"></td>
                    <td class="text-right" x-text="c.discount"></td>
                    <td class="text-right font-semibold" x-text="c.revenue"></td>
                  </tr>
                </template>
              </tbody>
            </table>
            <div class="table-empty" x-show="!data.coupons.length">No coupon has been used yet.</div>
          </section>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
