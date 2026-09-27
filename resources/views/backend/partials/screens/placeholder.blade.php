<template x-if="!$store.ui.isBuilt">
<div class="p-6">
  <div class="page-head">
    <div class="page-head-text">
      <div class="kicker" x-text="$store.ui.groupOf($store.ui.screen)"></div>
      <h2 x-text="$store.ui.crumb"></h2>
      <div class="page-subtitle">Not built yet</div>
    </div>
  </div>

  <div class="panel max-w-[580px]">
    <div class="panel-head">
      <span class="h-2.5 w-2.5 bg-accent"></span>
      Coming in a later part
    </div>
    <div class="panel-body text-sm leading-relaxed text-neutral-700">
      <p class="mb-4">
        The database table, model and permissions for
        <strong class="text-ink" x-text="$store.ui.crumb"></strong> already exist.
        The screen itself lands in a later part.
      </p>
      <button @click="$store.ui.go('dashboard')" class="btn btn-secondary">Back to dashboard</button>
    </div>
  </div>
</div>
</template>
