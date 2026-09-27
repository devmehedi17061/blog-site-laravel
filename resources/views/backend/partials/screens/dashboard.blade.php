<template x-if="$store.ui.screen === 'dashboard'">
<div x-data="dashboard" x-init="load()" class="p-4 sm:p-6">

  {{-- ── greeting and the state of the site ───────────────────────────── --}}
  <section class="dash-hero mb-6">
    <div class="min-w-0 flex-1">
      <div class="kicker !text-accent-300" x-text="greeting?.today ?? 'Overview'"></div>
      <h2 class="dash-hello" x-text="greeting ? `${greeting.hello}, ${greeting.name}` : 'Dashboard'"></h2>
      <p class="dash-hero-sub">
        Here is how <strong x-text="site?.name ?? 'the site'"></strong> is doing today.
        <span x-show="attention.length" x-text="warnCount ? `${warnCount} thing${warnCount === 1 ? '' : 's'} need${warnCount === 1 ? 's' : ''} your attention.` : ''"></span>
      </p>

      <div class="dash-status" x-show="site">
        <span class="dash-pill" :class="site?.live ? 'is-good' : 'is-bad'">
          <span class="dash-dot"></span><span x-text="site?.live ? 'Site is live' : 'Maintenance mode'"></span>
        </span>
        <span class="dash-pill" :class="site?.indexed ? 'is-good' : 'is-bad'">
          <span class="dash-dot"></span><span x-text="site?.indexed ? 'Visible to search engines' : 'Hidden from search engines'"></span>
        </span>
        <span class="dash-pill"><span class="dash-pill-k">Homepage</span><span x-text="site?.home"></span></span>
        <span class="dash-pill"><span class="dash-pill-k">Language</span><span x-text="site?.language"></span></span>
        <span class="dash-pill" x-show="site?.mail" :class="site?.mail === 'Log only' && 'is-bad'">
          <span class="dash-pill-k">Mail</span><span x-text="site?.mail"></span>
        </span>
      </div>
    </div>

    <div class="dash-hero-actions">
      <button class="btn btn-primary" x-show="$store.auth.can('posts.create')" @click="$store.ui.go('posts', 'new')">+ New post</button>
      <button class="btn dash-btn-light" x-show="$store.auth.can('pages.create')" @click="$store.ui.go('pages', 'new')">+ New page</button>
      <a class="btn dash-btn-light" :href="site?.url ?? '/'" target="_blank" rel="noopener">View site ↗</a>
      <button class="btn dash-btn-ghost" @click="load()" :disabled="loading" title="Reload the figures">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="loading && 'animate-spin'" aria-hidden="true">
          <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path>
          <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path>
        </svg>
        <span x-text="loading ? 'Loading…' : updatedLabel || 'Refresh'"></span>
      </button>
    </div>
  </section>

  <template x-if="error">
    <div class="alert mb-5" x-text="error"></div>
  </template>

  {{-- ── key figures ────────────────────────────────────────────────── --}}
  <div class="stat-grid mb-6">
    <template x-for="stat in stats" :key="stat.key">
      <button type="button" class="stat-tile dash-tile" :style="`background:${stat.bg};color:${stat.fg}`" @click="open(stat.key)"
              :title="`Open ${stat.label.toLowerCase()}`">
        <div class="stat-label" :style="`color:${stat.soft}`">
          <span class="h-px w-3" :style="`background:${stat.soft}`"></span><span x-text="stat.label"></span>
        </div>
        <div class="stat-value" x-text="stat.value.toLocaleString()"></div>
        <div class="stat-delta" :style="`color:${stat.soft}`" x-text="stat.sub"></div>
      </button>
    </template>

    <template x-if="loading && stats.length === 0">
      <div class="stat-tile bg-surface text-sm text-neutral-600">Loading figures…</div>
    </template>
  </div>

  {{-- ── activity over time + the to-do list ────────────────────────── --}}
  <div class="dash-row mb-6">
    <section class="panel dash-card flex-[2_1_520px]" x-show="trend.series.length">
      <div class="dash-card-head">
        <h4>Last 30 days</h4>
        <div class="dash-legend" role="group" aria-label="Show or hide a series">
          <template x-for="s in trend.series" :key="s.key">
            <button type="button" class="dash-legend-item" :class="hidden.includes(s.key) && 'is-off'" @click="toggleSeries(s.key)"
                    :aria-pressed="!hidden.includes(s.key)">
              <span class="dash-swatch" :style="`background:${seriesColor(s.key)}`"></span>
              <span x-text="s.label"></span>
              <strong class="tabular-nums" x-text="seriesTotal(s.key)"></strong>
            </button>
          </template>
        </div>
      </div>

      <div class="dash-chart">
        <div class="relative" @mouseleave="hover = null">
        {{-- A static viewBox: the HTML parser lowercases a bound ":viewBox" to
             "viewbox", which SVG ignores. Matches CHART in dashboard.js. --}}
        <svg viewBox="0 0 720 216" class="block" role="img"
             :aria-label="`Daily ${trend.series.map(s => s.label.toLowerCase()).join(', ')} for the last 30 days`" x-html="chartSvg"></svg>
        {{-- One layer over the plot: the pointer's position picks the day,
             and a tap does the same on a touch screen. --}}
        <div class="dash-chart-hits" :style="hoverStyle" @mousemove="hoverAt($event)" @click="hoverAt($event)"></div>
        <div class="dash-tip" x-show="hovered" x-transition.opacity.duration.100ms
             :style="hover !== null && `left:${Math.min(84, Math.max(16, (hover + 0.5) / trend.days.length * 100))}%`">
          <div class="font-bold" x-text="hovered ? `${hovered.weekday} ${hovered.label}` : ''"></div>
          <template x-for="s in shownSeries" :key="s.key">
            <div class="flex items-center gap-2">
              <span class="dash-swatch" :style="`background:${seriesColor(s.key)}`"></span>
              <span x-text="s.label"></span>
              <strong class="ml-auto pl-3 tabular-nums" x-text="hovered?.[s.key] ?? 0"></strong>
            </div>
          </template>
        </div>
        </div>
      </div>
    </section>

    <section class="panel dash-card flex-[1_1_320px]">
      <div class="dash-card-head">
        <h4>Needs attention</h4>
        <span class="tag" :class="warnCount ? 'tag-accent' : attention.length ? 'tag-neutral' : 'tag-success'"
              x-text="attention.length ? `${attention.length} item${attention.length === 1 ? '' : 's'}` : 'All clear'"></span>
      </div>

      <ul class="dash-todo">
        <template x-for="(item, i) in attention" :key="i">
          <li>
            <button type="button" class="dash-todo-item" :class="`is-${item.tone}`" @click="open(item.screen, item.param)" :disabled="!item.screen">
              <span class="dash-todo-mark" aria-hidden="true" x-text="item.tone === 'warn' ? '!' : 'i'"></span>
              <span class="min-w-0 flex-1">
                <span class="dash-todo-title" x-text="item.title"></span>
                <span class="dash-todo-detail" x-text="item.detail"></span>
              </span>
              <span class="dash-todo-go" x-show="item.screen" aria-hidden="true">→</span>
            </button>
          </li>
        </template>
        <li x-show="!loading && !attention.length" class="dash-empty">
          <span class="dash-done" aria-hidden="true">✓</span> Nothing to fix. The site is in good shape.
        </li>
      </ul>
    </section>
  </div>

  {{-- ── what the content looks like ───────────────────────────────────── --}}
  <div class="dash-row mb-6">
    <section class="panel dash-card flex-[1_1_300px]" x-show="content.posts || content.pages">
      <div class="dash-card-head"><h4>Content</h4></div>

      <template x-for="block in [{ key: 'posts', label: 'Posts', rows: content.posts }, { key: 'pages', label: 'Pages', rows: content.pages }].filter(b => b.rows)" :key="block.key">
        <button type="button" class="dash-status-block" @click="open(block.key)">
          <span class="flex items-baseline gap-2">
            <strong x-text="block.label"></strong>
            <span class="text-[12px] text-neutral-500" x-text="`${total(block.rows)} in total`"></span>
          </span>
          <span class="dash-stack" aria-hidden="true">
            <template x-for="row in block.rows" :key="row.status">
              <span :style="`width:${share(row.count, block.rows)}%;background:${statusColor(row.status)}`"></span>
            </template>
          </span>
          <span class="dash-stack-legend">
            <template x-for="row in block.rows" :key="row.status">
              <span><span class="dash-swatch" :style="`background:${statusColor(row.status)}`"></span><span x-text="`${row.count} ${row.status.toLowerCase()}`"></span></span>
            </template>
          </span>
        </button>
      </template>

      <div class="px-4 pt-1 pb-4" x-show="content.categories.length">
        <div class="dash-mini-head">Top categories</div>
        <template x-for="cat in content.categories" :key="cat.name">
          <div class="dash-hbar">
            <span class="truncate" x-text="cat.name"></span>
            <span class="dash-hbar-track"><span :style="`width:${cat.count / categoryMax * 100}%`"></span></span>
            <strong class="tabular-nums" x-text="cat.count"></strong>
          </div>
        </template>
        <p class="mt-2 text-[12px] text-neutral-500" x-show="content.uncategorised"
           x-text="`${content.uncategorised} post(s) have no category.`"></p>
      </div>
    </section>

    <section class="panel dash-card flex-[1_1_300px]" x-show="seo.length">
      <div class="dash-card-head">
        <h4>SEO coverage</h4>
        <span class="dash-score" x-show="seoScore !== null" :class="`is-${seoScore >= 80 ? 'good' : seoScore >= 40 ? 'fair' : 'poor'}`">
          <strong x-text="seoScore"></strong><span>/100</span>
        </span>
      </div>
      <div class="flex flex-col gap-3 p-4">
        <template x-for="row in seo" :key="row.label">
          <div>
            <div class="mb-1 flex items-baseline justify-between text-[13px]">
              <span x-text="row.label"></span>
              <span class="tabular-nums text-neutral-600" x-text="row.total ? `${row.have} of ${row.total}` : 'nothing yet'"></span>
            </div>
            <div class="dash-meter" :class="`is-${percentTone(row)}`"><span :style="`width:${percent(row)}%`"></span></div>
          </div>
        </template>
        <p class="text-[12px] leading-relaxed text-neutral-500">
          Across live posts. Open a post's SEO tab for its own checklist.
        </p>
      </div>
    </section>

    <section class="panel dash-card flex-[1_1_300px]">
      <div class="dash-card-head"><h4>Coming up</h4><span class="text-[12px] text-neutral-500">Scheduled to go live</span></div>
      <ul class="dash-list">
        <template x-for="item in upcoming" :key="`${item.type}-${item.id}`">
          <li>
            <button type="button" class="dash-list-item" @click="open(item.type, item.id)">
              <span class="dash-date" aria-hidden="true">
                <strong x-text="item.when.split(' ')[1]"></strong><span x-text="item.when.split(' ')[2]"></span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold" x-text="item.title"></span>
                <span class="block text-[12px] text-neutral-500" x-text="`${item.type === 'posts' ? 'Post' : 'Page'} · ${item.in}`"></span>
              </span>
            </button>
          </li>
        </template>
        <li x-show="!loading && !upcoming.length" class="dash-empty">Nothing is scheduled. Give a post a future date to plan ahead.</li>
      </ul>
    </section>
  </div>

  {{-- ── recent work and what came in ───────────────────────────────── --}}
  <div class="dash-row mb-6">
    <section class="panel dash-card flex-[3_1_460px]" x-show="recent.length || $store.auth.can('posts.view')">
      <div class="dash-card-head"><h4>Recently edited</h4></div>
      <div class="overflow-x-auto">
        <table class="data-table min-w-[480px]">
          <tbody>
            <template x-for="row in recent" :key="`${row.type}-${row.id}`">
              <tr class="cursor-pointer" @click="open(row.type, row.id)">
                <td class="w-[64px]"><span class="text-[11px] font-bold tracking-[0.08em] text-neutral-500 uppercase" x-text="row.kind"></span></td>
                <td class="cell-strong" x-text="row.title"></td>
                <td><span class="tag" :class="tagFor(row.status)" x-text="row.status"></span></td>
                <td class="cell-muted text-[13px]" x-text="row.author ?? '—'"></td>
                <td class="cell-muted cell-nowrap text-right text-[13px]" x-text="row.updated"></td>
              </tr>
            </template>
            <template x-if="!loading && !recent.length">
              <tr><td class="cell-muted">Nothing written yet.</td></tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel dash-card flex-[2_1_320px]" x-show="$store.auth.can('forms.view')">
      <div class="dash-card-head">
        <h4>Latest messages</h4>
        <button class="btn btn-ghost btn-sm" @click="open('inbox')">Inbox →</button>
      </div>
      <ul class="dash-list">
        <template x-for="msg in messages" :key="msg.id">
          <li>
            <button type="button" class="dash-list-item" @click="open('inbox')">
              <span class="dash-unread" :class="msg.unread && 'is-on'" :title="msg.unread ? 'Unread' : 'Read'"></span>
              <span class="min-w-0 flex-1">
                <span class="block truncate" :class="msg.unread && 'font-semibold'" x-text="msg.summary"></span>
                <span class="block text-[12px] text-neutral-500" x-text="`${msg.form} · ${msg.ago}`"></span>
              </span>
            </button>
          </li>
        </template>
        <li x-show="!loading && !messages.length" class="dash-empty">No form messages yet.</li>
      </ul>
    </section>
  </div>

  {{-- ── who did what, and the server ───────────────────────────────── --}}
  <div class="dash-row">
    <section class="panel dash-card flex-[3_1_460px]">
      <div class="dash-card-head"><h4>Recent activity</h4></div>
      <ol class="dash-feed">
        <template x-for="row in activity" :key="row.id">
          <li>
            <span class="dash-avatar" x-text="row.initials"></span>
            <span class="min-w-0 flex-1">
              <span class="block text-[13px]"><strong x-text="row.actor"></strong> <span x-text="row.action.toLowerCase()"></span></span>
              <span class="block truncate text-[12px] text-neutral-500" x-text="row.target"></span>
            </span>
            <span class="cell-nowrap text-[12px] text-neutral-500" x-text="row.when"></span>
          </li>
        </template>
        <li x-show="!loading && !activity.length" class="dash-empty">No activity recorded yet.</li>
      </ol>
    </section>

    <section class="panel dash-card flex-[2_1_320px]" x-show="system.length">
      <div class="dash-card-head"><h4>System health</h4></div>
      <template x-for="(row, i) in system" :key="row.k">
        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-[13px]" :class="i < system.length - 1 && 'border-b border-line'">
          <span class="flex items-center gap-2 text-neutral-600">
            <span class="dash-health" :class="row.ok ? 'is-good' : 'is-bad'" :title="row.ok ? 'OK' : 'Needs a look'"></span>
            <span x-text="row.k"></span>
          </span>
          <span class="text-right font-medium tabular-nums" :class="!row.ok && 'text-accent-700'" x-text="row.v"></span>
        </div>
      </template>
    </section>
  </div>
</div>
</template>
