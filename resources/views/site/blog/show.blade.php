{{-- /blog/{slug}. Drafts and scheduled posts are visible to admins only, as a preview. --}}
<x-layouts.site :title="$post->seo_title ?: $post->title" :description="$post->seo_description ?: $post->summary(160)"
    :image="$post->cover_image" og-type="article" :noindex="$preview">
    <article class="mx-auto max-w-3xl px-1 py-10 sm:px-4">
        @if ($preview)
            <x-alert variant="warning" class="mb-6">Preview: this post is {{ $post->status === 'published' ? 'scheduled for '.$post->published_at?->format('d M Y H:i') : 'a draft' }}. Only admins can see it.</x-alert>
        @endif

        <a href="{{ route('blog') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
            <x-glyph name="arrow-left" :size="14" /> {{ $settings['title'] }}
        </a>

        <header class="mt-6">
            <div class="flex flex-wrap items-center gap-2 text-sm text-ag-subtle">
                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toIso8601String() }}" class="font-mono">{{ $post->published_at->format('d M Y') }}</time>
                    <span aria-hidden="true">·</span>
                @endif
                {{ $post->readingMinutes() }} min read
                @if ($post->author)<span aria-hidden="true">·</span> {{ $post->author->name }}@endif
            </div>
            <h1 class="mt-3 text-[34px] font-medium leading-tight tracking-heading text-ag-ink sm:text-[44px]">{{ $post->title }}</h1>
            @if ($post->excerpt)
                <p class="mt-4 text-lg leading-relaxed text-ag-subtle">{{ $post->excerpt }}</p>
            @endif
            @if ($post->tags)
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($post->tags as $t)<a href="{{ route('blog', ['tag' => $t]) }}"><x-badge>{{ $t }}</x-badge></a>@endforeach
                </div>
            @endif
        </header>

        @unless ($preview)
            <x-share-buttons class="mt-6" :url="$post->url()" :title="$post->title" :text="$post->summary(140)" />
        @endunless

        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="" class="mt-8 w-full rounded-card shadow-ag">
        @endif

        <div class="mt-8 rounded-card bg-white p-6 shadow-ag sm:p-10">
            <x-markdown :source="$post->body" />
        </div>

        @unless ($preview)
            <div class="mt-6 rounded-card bg-white p-5 shadow-ag">
                <p class="mb-3 text-sm font-medium text-ag-ink">Found this useful? Share it with your team.</p>
                <x-share-buttons label="Share" :url="$post->url()" :title="$post->title" :text="$post->summary(140)" />
            </div>
        @endunless
    </article>

    @if ($more->isNotEmpty())
        <section class="mx-auto mt-6 max-w-6xl px-1 sm:px-4" aria-labelledby="more">
            <h2 id="more" class="mb-4 text-xl font-medium text-ag-ink">More from the blog</h2>
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($more as $p)
                    <a href="{{ $p->url() }}" class="lift rounded-card bg-white p-5 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                        <div class="font-mono text-xs text-ag-subtle">{{ $p->published_at->format('d M Y') }}</div>
                        <div class="mt-2 font-medium text-ag-ink">{{ $p->title }}</div>
                        <p class="mt-1 text-sm text-ag-subtle">{{ $p->summary(110) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.site>
