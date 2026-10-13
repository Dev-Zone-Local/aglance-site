<x-layouts.site title="Release notes">
    <div class="mx-auto max-w-4xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Changelog" title="Release notes" large>
            What changed in each version of the AtGlance CLI and the Management Console.
        </x-page-header>

        <nav class="mt-8 flex flex-wrap gap-2" aria-label="Filter by product">
            @foreach (['' => 'All products'] + $products as $key => $label)
                <a href="{{ route('releases', $key ? ['product' => $key] : []) }}"
                    @class([
                        'rounded-full px-4 py-2 text-sm transition-colors',
                        'bg-ag-ink text-white' => $product === ($key ?: null),
                        'bg-white text-ag-ink shadow-ag hover:text-ag-teal-text' => $product !== ($key ?: null),
                    ])
                    @if ($product === ($key ?: null)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mt-10 space-y-6">
            @forelse ($releases as $r)
                @php($url = \App\Support\Downloads::releaseUrl($r['product'], $r['version']))
                {{-- The id keeps old /releases#cli-2-1-0 links working. --}}
                <article id="{{ \App\Support\Downloads::anchor($r['product'], $r['version']) }}" class="relative scroll-mt-24 rounded-card bg-white p-6 shadow-ag transition-shadow hover:shadow-ag-strong">
                    <header class="flex flex-wrap items-center gap-2">
                        <x-glyph :name="$r['product'] === 'cli' ? 'terminal' : 'server'" :size="16" class="text-ag-teal" />
                        <span class="text-sm text-ag-subtle">{{ $r['product_name'] }}</span>
                        <a href="{{ $url }}" class="text-lg font-medium text-ag-ink after:absolute after:inset-0 after:rounded-card hover:text-ag-teal-text">v{{ $r['version'] }}</a>
                        @if ($r['latest'])
                            <x-badge variant="success">Latest</x-badge>
                        @endif
                        @if ($r['type'])
                            <x-badge :variant="$r['type'] === 'security' ? 'danger' : ($r['type'] === 'beta' ? 'warning' : 'info')">{{ $types[$r['type']] ?? $r['type'] }}</x-badge>
                        @endif
                        @if ($r['released_at'])
                            <time datetime="{{ $r['released_at'] }}" class="ml-auto font-mono text-xs text-ag-subtle">{{ \Illuminate\Support\Carbon::parse($r['released_at'])->format('d M Y') }}</time>
                        @endif
                    </header>

                    @if ($r['title'])
                        <h2 class="mt-3 text-xl font-medium tracking-heading text-ag-ink">{{ $r['title'] }}</h2>
                    @endif
                    @if ($r['summary'])
                        <p class="mt-2 text-[15px] leading-relaxed text-ag-subtle">{{ $r['summary'] }}</p>
                    @endif

                    @if (! $r['summary'] && ! $r['notes'])
                        <p class="mt-3 text-sm text-ag-subtle">Maintenance release.</p>
                    @endif

                    <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text">
                        Full release notes <x-glyph name="arrow-right" :size="14" />
                    </span>
                </article>
            @empty
                <x-card flat class="text-center text-sm text-ag-subtle">No releases published yet.</x-card>
            @endforelse
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <x-button :href="route('dashboard.install')">Install or update <x-glyph name="arrow-right" /></x-button>
            <x-button :href="route('docs')" variant="white">Docs</x-button>
        </div>
    </div>
</x-layouts.site>
