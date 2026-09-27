<article class="panel flex flex-col transition duration-150 hover:-translate-y-0.5 hover:shadow-md">
  <div class="flex items-center gap-2 bg-neutral-900 px-4 py-2.5 font-heading text-[10px] font-bold tracking-[0.1em] text-neutral-300 uppercase">
    <span class="h-2 w-2 bg-accent"></span>
    <span>{{ $post->category?->name ?? 'Uncategorised' }}</span>
    <time class="ml-auto font-sans text-[11px] font-normal tracking-normal text-neutral-400 normal-case"
          datetime="{{ $post->published_at?->toDateString() }}">
      {{ $site->date($post->published_at) }}
    </time>
  </div>

  @if ($post->featuredImage)
    <a href="{{ route('blog.show', $post->slug) }}" class="block border-b border-line">
      <img src="{{ $post->featuredImage->url }}"
           alt="{{ $post->featuredImage->alt_text ?: $post->title }}"
           {!! \App\Support\ResponsiveImages::attributes($post->featuredImage, '(max-width: 720px) 100vw, 360px') !!}
           class="h-[160px] w-full object-cover">
    </a>
  @endif

  <div class="flex flex-1 flex-col gap-2.5 p-5">
    <h3 class="!mb-0 text-[19px]">
      <a href="{{ route('blog.show', $post->slug) }}" class="text-ink no-underline transition hover:text-accent">
        {{ $post->title }}
      </a>
    </h3>

    @if ($post->excerpt)
      <p class="flex-1 text-[13px] leading-relaxed text-neutral-600">{{ $post->excerpt }}</p>
    @endif

    <div class="mt-2 flex items-center gap-2 border-t border-line pt-3 text-[12px] text-neutral-600">
      <span>{{ $post->author?->name ?? 'Unknown' }}</span>
      <a href="{{ route('blog.show', $post->slug) }}"
         class="ml-auto font-heading text-[12px] font-bold text-accent no-underline hover:text-accent-700">
        Read →
      </a>
    </div>
  </div>
</article>
