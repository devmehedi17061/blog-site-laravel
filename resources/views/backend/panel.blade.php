@extends('backend.layout')

@section('title', 'Admin panel')

@section('body')
  {{-- The signed-in user is injected here rather than fetched, so the panel
       paints without a round trip and Alpine never handles credentials. --}}
  <script>
    window.__ADMIN__ = @json($panel);
    // The public site's script (sliders …), for the section previews.
    window.__SITE_SCRIPT__ = @json(\Illuminate\Support\Facades\Vite::asset('resources/js/site.js'));
    // Settings → Design, for the section previews to look like the site.
    window.__SITE_DESIGN__ = @json(['css' => app(\App\Support\Design::class)->css(), 'fonts' => app(\App\Support\Design::class)->fontsUrl()]);
  </script>

  <div x-data x-cloak class="flex h-screen flex-col overflow-hidden bg-bg text-ink">
    @include('backend.partials.layout.header')

    <div class="flex min-h-0 flex-1">
      @include('backend.partials.layout.sidebar')

      <main class="min-w-0 flex-1 overflow-y-auto">
        @include('backend.partials.screens.dashboard')
        @include('backend.partials.screens.posts')
        @include('backend.partials.screens.post-editor')
        @include('backend.partials.screens.media')
        @include('backend.partials.screens.pages')
        @include('backend.partials.screens.page-editor')
        @include('backend.partials.screens.section-library')
        @include('backend.partials.screens.forms')
        @include('backend.partials.screens.form-builder')
        @include('backend.partials.screens.header-footer')
        @include('backend.partials.screens.inbox')
        @include('backend.partials.screens.menu')
        @include('backend.partials.screens.trash')
        @include('backend.partials.screens.categories')
        @include('backend.partials.screens.users')
        @include('backend.partials.screens.roles')
        @include('backend.partials.screens.settings')
        @include('backend.partials.screens.security')
        @include('backend.partials.screens.account')
        @include('backend.partials.screens.placeholder')
      </main>
    </div>

    @include('backend.partials.layout.footer')
    @include('backend.partials.layout.toast')
    @include('backend.partials.screens.media-picker')
    @include('backend.partials.layout.dialog')
  </div>

  <form id="logout-form" method="POST" action="{{ route('admin.logout') }}" class="hidden">@csrf</form>
@endsection
