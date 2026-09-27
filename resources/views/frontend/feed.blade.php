{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>{{ $site->name() }}</title>
    <link>{{ url('/') }}</link>
    <description>{{ $site->description() ?: $site->homeTitle() }}</description>
    <language>{{ $site->language() }}</language>
    <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
@if ($posts->first())
    <lastBuildDate>{{ $posts->first()->published_at->toRssString() }}</lastBuildDate>
@endif
@foreach ($posts as $post)
    <item>
      <title>{{ $post->title }}</title>
      <link>{{ route('blog.show', $post->slug) }}</link>
      <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
      <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
@if ($post->author)
      <dc:creator>{{ $post->author->name }}</dc:creator>
@endif
@if ($post->category)
      <category>{{ $post->category->name }}</category>
@endif
      <description>{{ $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 280)) }}</description>
    </item>
@endforeach
  </channel>
</rss>
