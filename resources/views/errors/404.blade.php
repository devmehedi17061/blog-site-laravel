{{--
  Page not found, in the site's own clothes: header, menu, footer. A lost
  visitor should see they are still on the right site and have a way on.
--}}
@extends('frontend.layout')

@section('title', $site->title('Page not found'))
@section('robots', 'noindex, follow')

@section('content')
  <div class="mx-auto max-w-[620px] py-10 text-center">
    <div class="mb-4 inline-block bg-accent px-3 py-0.5 font-heading text-[13px] font-extrabold tracking-[0.08em] text-white">404</div>
    <h1 class="mb-3 font-heading text-[34px] font-extrabold leading-tight">We could not find that page</h1>
    <p class="mb-8 text-[16px] leading-relaxed text-neutral-600">
      The address may be mistyped, or the page has moved or been removed.
      Try searching, or start from one of these.
    </p>

    <form action="{{ route('blog.index') }}" method="GET" class="mx-auto mb-8 flex max-w-[440px] gap-2">
      <label for="notfound-search" class="sr-only">Search posts</label>
      <input id="notfound-search" type="search" name="q" class="input flex-1" placeholder="Search posts…">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <div class="flex flex-wrap justify-center gap-3">
      <a href="{{ route('home') }}" class="btn btn-primary">Go to the homepage</a>
      <a href="{{ route('blog.index') }}" class="btn btn-secondary">Read the blog</a>
    </div>
  </div>
@endsection
