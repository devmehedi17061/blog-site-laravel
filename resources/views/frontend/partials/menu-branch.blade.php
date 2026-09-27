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
    @php($item = $node['item'])

    <div class="site-menu-node">
      <a href="{{ $item->url }}" class="site-menu-link"
         @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>
        {!! \App\Support\MenuIcons::svg($item->icon) !!}
        <span class="site-menu-text">{{ $item->label }}</span>

        @if ($node['children'])
          <span class="site-menu-more" aria-hidden="true">›</span>
        @endif
      </a>

      @if ($node['children'])
        @include('frontend.partials.menu-branch', ['branch' => $node['children']])
      @endif
    </div>
  @endforeach
</div>
