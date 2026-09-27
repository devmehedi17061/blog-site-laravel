<header class="flex min-h-[58px] flex-none flex-wrap items-stretch border-b border-line-strong bg-surface shadow-xs">

  <div class="flex flex-[0_0_auto] items-center gap-3 bg-accent pr-5 text-white">
    <button @click="$store.ui.toggleSidebar()" title="Toggle menu"
            class="grid w-11 flex-none cursor-pointer place-items-center self-stretch border-0 border-r border-white/25 bg-transparent text-white transition hover:bg-accent-600">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path>
      </svg>
    </button>
    <span class="font-heading text-[17px] font-extrabold tracking-tight whitespace-nowrap">CMS Admin</span>
  </div>

  <div class="flex min-w-0 flex-[1_1_320px] items-center gap-3 px-5 py-2">
    <span class="font-heading text-[12px] font-bold tracking-[0.09em] whitespace-nowrap text-neutral-600 uppercase"
          x-text="$store.ui.crumb"></span>

    <span class="inline-flex h-[26px] items-center bg-neutral-100 px-2.5 text-[10px] font-bold tracking-[0.1em] whitespace-nowrap text-neutral-600 uppercase shadow-[inset_0_0_0_1px_var(--color-line-strong)]">
      {{ config('app.env') }}
    </span>

    {{-- Really clears it now (CacheController); only for people who may
         change settings. --}}
    <button x-show="$store.auth.can('settings.update')" @click="$store.ui.clearCache()"
            :disabled="$store.ui.clearingCache" class="btn btn-secondary btn-sm ml-auto"
            x-text="$store.ui.clearingCache ? 'Clearing…' : 'Clear cache'">
      Clear cache
    </button>

    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
      View site ↗
    </a>
  </div>

  <div class="flex items-center gap-2.5 border-l border-line px-5 py-2">
    {{-- Your own name opens My account: password, two-factor, sessions. --}}
    <button type="button" class="account-link" @click="$store.ui.go('account')" title="My account — password, two-factor, sessions">
      <span class="grid h-[32px] w-[32px] flex-none place-items-center bg-neutral-900 font-heading text-[11px] font-bold text-white"
            x-text="$store.auth.user?.initials"></span>
      <span class="flex flex-col items-start leading-tight">
        <span class="text-[13px] font-semibold whitespace-nowrap" x-text="$store.auth.user?.name"></span>
        <span class="text-[11px] whitespace-nowrap text-neutral-600" x-text="$store.auth.user?.role?.name"></span>
      </span>
    </button>
    <button @click="$store.auth.logout()" title="Sign out" class="btn btn-secondary btn-icon !h-[32px] !w-[32px] ml-1">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path>
      </svg>
    </button>
  </div>
</header>
