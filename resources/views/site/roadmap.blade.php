{{-- /roadmap: items from content/roadmap.json in three columns. --}}
@php
    $tag = ['cli' => ['terminal', 'CLI'], 'console' => ['server', 'Console'], 'both' => ['layers', 'CLI + Console']];
    $tone = ['planned' => 'default', 'in_progress' => 'warning', 'released' => 'success'];
@endphp
<x-layouts.site title="Roadmap" description="What is planned, in progress and released for the AtGlance CLI and the Management Console.">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Roadmap" title="What we are building" large>
            Planned, in progress and released work for the CLI and the Management Console. Dates are not promised until a release is out.
        </x-page-header>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            <nav class="flex flex-wrap gap-2" aria-label="Filter by product">
                @foreach (['' => 'All', 'cli' => 'CLI', 'console' => 'Console'] as $key => $label)
                    <a href="{{ route('roadmap', $key ? ['product' => $key] : []) }}" @if ($product === ($key ?: null)) aria-current="page" @endif
                        @class([
                            'rounded-full px-4 py-2 text-sm transition-colors',
                            'bg-ag-ink text-white' => $product === ($key ?: null),
                            'bg-white text-ag-ink shadow-ag hover:text-ag-teal-text' => $product !== ($key ?: null),
                        ])>{{ $label }}</a>
                @endforeach
            </nav>
            @if ($updated)
                <span class="ml-auto font-mono text-xs text-ag-subtle">Updated {{ \Illuminate\Support\Carbon::parse($updated)->format('d M Y') }}</span>
            @endif
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            @foreach ($columns as $status => $label)
                <section aria-labelledby="col-{{ $status }}" class="rounded-[22px] bg-ag-surface/70 p-4">
                    <h2 id="col-{{ $status }}" class="flex items-center gap-2 px-1 text-sm font-medium text-ag-ink">
                        <x-badge :variant="$tone[$status]">{{ $label }}</x-badge>
                        <span class="text-ag-subtle">{{ count($byStatus[$status]) }}</span>
                    </h2>
                    <ul class="mt-4 space-y-3">
                        @forelse ($byStatus[$status] as $item)
                            @php([$icon, $name] = $tag[$item['product'] ?? 'both'] ?? $tag['both'])
                            <li class="rounded-card bg-white p-4 shadow-ag">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-ag-subtle">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-ag-surface px-2 py-0.5 font-medium text-ag-ink"><x-glyph :name="$icon" :size="12" /> {{ $name }}</span>
                                    @if (! empty($item['target']))<span class="font-mono">{{ $item['target'] }}</span>@endif
                                </div>
                                <h3 class="mt-2 text-[15px] font-medium leading-snug text-ag-ink">{{ $item['title'] }}</h3>
                                @if (! empty($item['summary']))
                                    <p class="mt-1.5 text-sm leading-relaxed text-ag-subtle">{{ $item['summary'] }}</p>
                                @endif
                                @if (! empty($item['release_url']))
                                    <a href="{{ $item['release_url'] }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
                                        In v{{ $item['release'] }} <x-glyph name="arrow-right" :size="14" />
                                    </a>
                                @elseif (! empty($item['release']))
                                    <span class="mt-3 inline-block font-mono text-xs text-ag-subtle">v{{ $item['release'] }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-1 text-sm text-ag-subtle">Nothing here yet.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        <div class="mt-10 flex flex-wrap gap-3">
            <x-button :href="route('releases')">Release notes <x-glyph name="arrow-right" /></x-button>
            <x-button :href="route('contact')" variant="white">Suggest a feature</x-button>
        </div>
    </div>
</x-layouts.site>
