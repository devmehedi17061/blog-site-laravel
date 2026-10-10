{{--
  The student panel's frame: the sidebar and the page heading. Panel pages
  extend this and fill:
    @section('panel_title')    the heading
    @section('panel_kicker')   the small line above it (optional)
    @section('panel_actions')  buttons beside the heading (optional)
    @section('panel')          the page
--}}
@extends('frontend.layout')

@section('robots', 'noindex, nofollow')
@section('main_class', 'w-full')

@php
  $navIcon = [
    'overview' => '<path d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z"/>',
    'courses' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v16H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v4H6.5A2.5 2.5 0 0 1 4 19.5"/>',
    'payments' => '<rect x="2" y="5" width="20" height="14"/><path d="M2 10h20M6 15h4"/>',
    'certificates' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
    'wishlist' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
    'notifications' => '<path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
    'account' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  ];
  $nav = [
    ['key' => 'overview', 'label' => 'Overview', 'route' => 'student.dashboard', 'count' => null],
    ['key' => 'courses', 'label' => 'My courses', 'route' => 'student.courses', 'count' => $panelCounts['courses'] ?? null],
    ['key' => 'payments', 'label' => 'Payments', 'route' => 'student.payments', 'count' => $panelCounts['payments'] ?? null, 'alert' => true],
    ['key' => 'certificates', 'label' => 'Certificates', 'route' => 'student.certificates', 'count' => $panelCounts['certificates'] ?? null],
    ['key' => 'wishlist', 'label' => 'Wishlist', 'route' => 'student.wishlist', 'count' => $panelCounts['wishlist'] ?? null],
    ['key' => 'notifications', 'label' => 'Notifications', 'route' => 'student.notifications', 'count' => $panelCounts['notifications'] ?? null, 'alert' => true],
    ['key' => 'account', 'label' => 'Account', 'route' => 'student.account', 'count' => null],
  ];
@endphp

@section('content')
<div class="sp">
  <aside class="sp-side" aria-label="{{ __('Student panel') }}">
    <div class="sp-profile">
      <span class="sp-avatar">{{ $panelUser->initials }}</span>
      <span class="min-w-0">
        <strong class="block truncate">{{ $panelUser->name }}</strong>
        <span class="block truncate text-[12px] text-neutral-400">{{ $panelUser->email }}</span>
      </span>
    </div>

    <nav class="sp-nav">
      @foreach ($nav as $item)
        @php $on = request()->routeIs($item['route'].'*') || ($item['key'] === 'payments' && request()->routeIs('student.receipt')); @endphp
        <a href="{{ route($item['route']) }}" class="sp-nav-item {{ $on ? 'is-on' : '' }}" @if ($on) aria-current="page" @endif>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">{!! $navIcon[$item['key']] !!}</svg>
          <span>{{ __($item['label']) }}</span>
          @if ($item['count'])
            <span class="sp-badge {{ ($item['alert'] ?? false) ? 'is-alert' : '' }}">{{ $item['count'] }}</span>
          @endif
        </a>
      @endforeach
    </nav>

    <div class="sp-side-foot">
      <a href="{{ route('courses.index') }}" class="btn btn-primary w-full">{{ __('Browse courses') }}</a>
      @if ($panelUser->isStaff())
        <a href="{{ route('admin.panel') }}" class="btn btn-secondary w-full">{{ __('Admin panel') }}</a>
      @endif
      <form method="POST" action="{{ route('student.logout') }}">
        @csrf
        <button class="sp-signout">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          {{ __('Sign out') }}
        </button>
      </form>
    </div>
  </aside>

  <section class="sp-main">
    <header class="sp-head">
      <div class="min-w-0 flex-1">
        <div class="kicker mb-1">@yield('panel_kicker', __('Student panel'))</div>
        <h1 class="!mb-0 text-[clamp(24px,3vw,32px)]">@yield('panel_title')</h1>
      </div>
      @hasSection('panel_actions')
        <div class="flex flex-wrap items-center gap-2">@yield('panel_actions')</div>
      @endif
    </header>

    @include('frontend.partials.flash')
    @yield('panel')
  </section>
</div>
@endsection
