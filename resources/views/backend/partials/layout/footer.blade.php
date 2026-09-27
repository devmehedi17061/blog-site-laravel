<footer class="flex flex-none flex-wrap items-center gap-x-5 gap-y-2 border-t border-neutral-950 bg-neutral-900 px-5 py-2.5 text-[11px] text-neutral-500">
  <span class="mr-auto">{{ config('app.name') }} — Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}</span>
  <span class="inline-flex items-center gap-2 text-neutral-300">
    <span class="h-1.5 w-1.5 bg-accent"></span>
    <span x-text="`Signed in as ${$store.auth.user?.name ?? ''}`"></span>
  </span>
</footer>
