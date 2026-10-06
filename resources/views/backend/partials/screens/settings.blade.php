<template x-if="$store.ui.screen === 'settings'">
<div x-data="settings" class="p-6">

  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker">System</div>
      <h2>Settings</h2>
      <div class="page-subtitle">How the site looks, sends mail, keeps out spam, sells courses and loads on the front end</div>
    </div>
  </div>

  <div class="settings-tabs" role="tablist">
    <template x-for="item in tabs" :key="item.key">
      <button type="button" role="tab" class="settings-tab" :class="tab === item.key && 'is-active'"
              :aria-selected="tab === item.key" @click="openTab(item.key)">
        <span class="settings-tab-icon" aria-hidden="true">
          <template x-if="item.key === 'site'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"></path></svg>
          </template>
          <template x-if="item.key === 'email'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14"></rect><path d="m3 7 9 6 9-6"></path></svg>
          </template>
          <template x-if="item.key === 'spam'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6Z"></path><path d="m9 12 2 2 4-4"></path></svg>
          </template>
          <template x-if="item.key === 'design'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.6-.9 1.2-1.8-.5-1-.1-2.2 1.1-2.2H17a4 4 0 0 0 4-4c0-5-4-10-9-10Z"></path><circle cx="7.5" cy="11" r="1"></circle><circle cx="10" cy="7" r="1"></circle><circle cx="15" cy="7.5" r="1"></circle></svg>
          </template>
          <template x-if="item.key === 'frontend'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12"></rect><path d="M8 20h8M12 16v4"></path></svg>
          </template>
          <template x-if="item.key === 'lms'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c3 2 9 2 12 0v-5"></path></svg>
          </template>
          <template x-if="item.key === 'redirects'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h11a5 5 0 0 1 0 10H9"></path><path d="m8 3-4 4 4 4"></path></svg>
          </template>
        </span>
        <span x-text="item.label"></span>
      </button>
    </template>
  </div>

  <template x-if="error">
    <div class="alert mb-4" x-text="error"></div>
  </template>

  <div x-show="loading" class="panel table-empty">Loading settings…</div>

  <template x-if="!loading && !can.update">
    <div class="role-note mb-4">You can look at the settings but not change them.</div>
  </template>

  {{-- tabs still to come --}}
  <template x-if="!loading && !isBuilt(tab)">
    <div class="panel">
      <div class="panel-body text-sm text-neutral-700">
        <strong class="text-ink" x-text="tabs.find(t => t.key === tab)?.label"></strong> is the next step of Settings and is not built yet.
      </div>
    </div>
  </template>

  <template x-if="!loading && tab === 'site' && site">
    @include('backend.partials.screens.settings.site')
  </template>

  <template x-if="!loading && tab === 'redirects'">
    @include('backend.partials.screens.settings.redirects')
  </template>

  <template x-if="!loading && tab === 'design' && design">
    @include('backend.partials.screens.settings.design')
  </template>

  <template x-if="!loading && tab === 'frontend' && frontend">
    @include('backend.partials.screens.settings.frontend')
  </template>

  <template x-if="!loading && tab === 'lms' && lms">
    @include('backend.partials.screens.settings.lms')
  </template>

  <template x-if="!loading && tab === 'spam' && spam">
    @include('backend.partials.screens.settings.spam')
  </template>

  <template x-if="!loading && tab === 'email' && mail">
    @include('backend.partials.screens.settings.email')
  </template>
</div>
</template>
