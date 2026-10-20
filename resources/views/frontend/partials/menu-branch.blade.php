@php
  /**
   * One level of the menu below the top bar, and every level under it.
   *
   * Recursive: a node with children includes this partial again, which is how
   * the third level becomes a flyout off the second without the header
   * knowing how deep the menu goes.
   *
   * @var array<int, array{item: \App\Models\MenuItem, children: array}> $branch
   */
@endphp

<div class="site-menu-panel">
  @foreach ($branch as $node)
    @php $item = $node['item']; @endphp

    <div class="site-menu-node">
      <a @if ($item->href()) href="{{ $item->href() }}" @endif class="site-menu-link"
         @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>
        {!! \App\Support\MenuIcons::svg($item->icon) !!}
        <span class="site-menu-text">{{ $item->label }}</span>

        @if ($node['children'])
          <span class="site-menu-more" aria-hidden="true">›</span>
        @endif
      </a>

      @if ($node['children'])
        {{-- Opens this level in place on a touch screen (see site-parts/token). --}}
        <button type="button" class="site-menu-expand" aria-expanded="false" aria-label="Show the {{ $item->label }} menu">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        @include('frontend.partials.menu-branch', ['branch' => $node['children']])
      @endif
    </div>
  @endforeach
</div>
