@php
  /**
   * The markup behind one site token (App\Support\SitePartRenderer::TOKENS).
   * Class names start sp- so a header's own CSS can restyle any of them.
   *
   * @var string $token
   * @var array  $menus ['Header' => tree, 'Footer' => tree]
   */
  $logo = $site->get('logo');
  $home = route('home');
@endphp
@switch($token)
  @case('site_brand')
    <a href="{{ $home }}" class="sp-brand" aria-label="{{ $site->name() }} — home">
      @if ($logo)
        <img src="{{ $logo }}" alt="{{ $site->get('show_name') ? '' : $site->name() }}" class="sp-logo-img">
      @else
        <span class="sp-logo-mark" aria-hidden="true">{{ $site->initials() }}</span>
      @endif
      @if (! $logo || $site->get('show_name'))
        <span class="sp-brand-name">{{ $site->name() }}</span>
      @endif
    </a>
    @break

  @case('site_logo')
    <a href="{{ $home }}" class="sp-logo" aria-label="{{ $site->name() }} — home">
      @if ($logo)
        <img src="{{ $logo }}" alt="{{ $site->name() }}" class="sp-logo-img">
      @else
        <span class="sp-logo-mark" aria-hidden="true">{{ $site->initials() }}</span>
      @endif
    </a>
    @break

  @case('menu_header')
    @if ($menus['Header'] ?? [])
      <nav class="sp-menu" aria-label="Main">
        <div class="site-menu">
          @foreach ($menus['Header'] as $node)
            @php $item = $node['item']; @endphp
            <div class="site-menu-node site-menu-top">
              <a @if ($item->url) href="{{ $item->url }}" @endif class="site-menu-link site-menu-top-link" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>
                {!! \App\Support\MenuIcons::svg($item->icon) !!}
                <span class="site-menu-text">{{ $item->label }}</span>
                @if ($node['children'] && ($caret ?? 'triangle') !== 'none')
                  <span class="site-menu-caret" data-caret="{{ $caret ?? 'triangle' }}" aria-hidden="true">@include('frontend.partials.site-parts.caret', ['style' => $caret ?? 'triangle'])</span>
                @endif
              </a>
              @if ($node['children'])
                @include('frontend.partials.menu-branch', ['branch' => $node['children']])
              @endif
            </div>
          @endforeach
        </div>
      </nav>
    @endif
    @break

  @case('menu_footer')
    @if ($menus['Footer'] ?? [])
      <nav class="sp-menu" aria-label="Footer">
        <ul class="sp-list">
          @foreach ($menus['Footer'] as $node)
            @php $item = $node['item']; @endphp
            <li>
              <a @if ($item->url) href="{{ $item->url }}" @endif @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>
                {!! \App\Support\MenuIcons::svg($item->icon, 'menu-icon', 14) !!}<span>{{ $item->label }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      </nav>
    @endif
    @break

  @case('menu_toggle')
    <button type="button" class="menu-toggle" data-menu-toggle aria-expanded="false" aria-label="Open the menu">
      <svg class="menu-toggle-open" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
      <svg class="menu-toggle-close" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
    </button>
    @break

  @case('search')
    <form action="{{ route('blog.index') }}" method="GET" role="search" class="sp-search">
      <input type="search" name="q" value="{{ request('q') }}" placeholder="Search posts…" aria-label="Search">
      <button type="submit">Search</button>
    </form>
    @break

  @case('social')
    @php $links = $site->socialLinks(); @endphp
    @if ($links)
      <nav class="sp-social" aria-label="Social media">
        @foreach ($links as $link)
          <a href="{{ $link['url'] }}" target="_blank" rel="noopener me" title="{{ $link['label'] }}" aria-label="{{ $link['label'] }}">
            @include('frontend.partials.social-icon', ['network' => $link['key']])
          </a>
        @endforeach
      </nav>
    @endif
    @break

  @case('contact_phone')
    @if ($site->get('phone'))
      <a class="sp-contact" href="tel:{{ preg_replace('/[^\d+]/', '', (string) $site->get('phone')) }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"></path></svg>
        <span>{{ $site->get('phone') }}</span>
      </a>
    @endif
    @break

  @case('contact_email')
    @if ($site->get('contact_email'))
      <a class="sp-contact" href="mailto:{{ $site->get('contact_email') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1"></rect><path d="m3 7 9 6 9-6"></path></svg>
        <span>{{ $site->get('contact_email') }}</span>
      </a>
    @endif
    @break

  @case('contact_address')
    @if ($site->get('address'))
      <span class="sp-contact sp-address">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
        <span class="whitespace-pre-line">{{ $site->get('address') }}</span>
      </span>
    @endif
    @break
@endswitch
