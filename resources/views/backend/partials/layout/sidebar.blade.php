{{-- While the account owes two-factor setup or a new password, the rest of
     the panel is out of reach — shown, but not usable. --}}
<nav class="scroll-dark flex flex-none flex-col gap-5 overflow-y-auto border-r border-neutral-950 bg-neutral-900 pt-5 pb-6 transition-[width] duration-150"
     :class="$store.ui.owes && 'pointer-events-none opacity-40'" :aria-disabled="!!$store.ui.owes"
     :style="`width:${$store.ui.sidebarWidth}px`">

  <template x-for="group in $store.ui.groups" :key="group.label">
    {{-- A group whose every entry is gated away leaves no orphan heading. --}}
    <div class="flex flex-col gap-px"
         x-show="group.items.some(i => !i.permission || $store.auth.can(i.permission))">

      <div class="nav-group-label" x-show="$store.ui.showLabels">
        <span class="h-px w-2.5 bg-accent"></span><span x-text="group.label"></span>
      </div>

      <template x-for="item in group.items.filter(i => !i.permission || $store.auth.can(i.permission))" :key="item.key">
        <button class="nav-item" :class="$store.ui.isActive(item.key) && 'nav-item-active'"
                @click="$store.ui.go(item.key)" :title="item.label">
          <span class="nav-mono" x-text="item.mono"></span>
          <span x-show="$store.ui.showLabels" class="overflow-hidden whitespace-nowrap text-ellipsis" x-text="item.label"></span>
          <span class="nav-count"
                x-show="$store.ui.showLabels && $store.ui.countFor(item.key) !== undefined"
                x-text="$store.ui.countFor(item.key)"></span>
        </button>
      </template>
    </div>
  </template>
</nav>
