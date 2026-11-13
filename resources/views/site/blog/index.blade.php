{{-- /blog: published posts, newest first, filter by tag. Settings: Admin → Blog → Blog settings. --}}
<x-layouts.site :title="$tag ? $settings['title'].': '.$tag : $settings['title']" :description="$settings['intro']">
    <div class="mx-auto max-w-6xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Blog" :title="$settings['title']" large>{{ $settings['intro'] }}</x-page-header>

        @if ($tags->isNotEmpty())
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="Tags">
                <a href="{{ route('blog') }}" @if (! $tag) aria-current="page" @endif
                    @class(['rounded-full px-4 py-2 text-sm transition-colors', 'bg-ag-ink text-white' => ! $tag, 'bg-white text-ag-ink shadow-ag hover:text-ag-teal-text' => $tag])>All</a>
                @foreach ($tags as $t)
                    <a href="{{ route('blog', ['tag' => $t]) }}" @if ($tag === $t) aria-current="page" @endif
                        @class(['rounded-full px-4 py-2 text-sm transition-colors', 'bg-ag-ink text-white' => $tag === $t, 'bg-white text-ag-ink shadow-ag hover:text-ag-teal-text' => $tag !== $t])>{{ $t }}</a>
                @endforeach
                @if ($settings['rss'])
                    <a href="{{ route('blog.feed') }}" class="ml-auto inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm text-ag-subtle hover:text-ag-teal-text">RSS</a>
                @endif
            </nav>
        @endif

        @if ($posts->isEmpty())
            <x-card flat class="mt-10 text-center text-sm text-ag-subtle">No posts yet.</x-card>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $i => $post)
                    <article @class(['lift relative flex flex-col overflow-hidden rounded-card bg-white shadow-ag', 'sm:col-span-2 lg:col-span-3 lg:flex-row' => $i === 0 && $posts->onFirstPage() && ! $tag])>
                        @php($wide = $i === 0 && $posts->onFirstPage() && ! $tag)
                        <div @class(['aspect-[16/9] w-full shrink-0 bg-gradient-to-br from-[#20262d] to-[#14171b]', 'lg:aspect-auto lg:w-1/2' => $wide])>
                            @if ($post->coverUrl())
                                <img src="{{ $post->coverUrl() }}" alt="" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-ag-subtle">
                                <time datetime="{{ $post->published_at->toIso8601String() }}" class="font-mono">{{ $post->published_at->format('d M Y') }}</time>
                                <span aria-hidden="true">·</span> {{ $post->readingMinutes() }} min read
                                @foreach (array_slice($post->tags ?? [], 0, 2) as $t)<x-badge>{{ $t }}</x-badge>@endforeach
                            </div>
                            <h2 @class(['mt-3 font-medium leading-snug tracking-heading text-ag-ink', 'text-2xl sm:text-3xl' => $wide, 'text-lg' => ! $wide])>
                                <a href="{{ $post->url() }}" class="after:absolute after:inset-0 hover:text-ag-teal-text">{{ $post->title }}</a>
                            </h2>
                            <p class="mt-2 text-[15px] leading-relaxed text-ag-subtle">{{ $post->summary($wide ? 260 : 150) }}</p>
                            <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-medium text-ag-teal-text">Read <x-glyph name="arrow-right" :size="14" /></span>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layouts.site>
