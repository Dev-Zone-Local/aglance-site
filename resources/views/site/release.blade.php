@php
    use App\Support\Downloads;
    use Illuminate\Support\Carbon;

    $r = $release;
    $title = $r['product_name'].' v'.$r['version'];
    $typeVariant = fn (?string $t) => $t === 'security' ? 'danger' : ($t === 'beta' ? 'warning' : 'info');
@endphp
<x-layouts.site :title="$title" :description="$r['summary'] ?: ($r['title'] ?: 'What changed in '.$title.'.')">
    <div class="mx-auto max-w-4xl px-1 py-10 sm:px-4">
        <a href="{{ route('releases', ['product' => $r['product']]) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
            <x-glyph name="arrow-left" :size="14" /> All {{ $r['product_name'] }} releases
        </a>

        <header class="mt-6">
            <div class="flex flex-wrap items-center gap-2 text-sm text-ag-subtle">
                <x-glyph :name="$r['product'] === 'cli' ? 'terminal' : 'server'" :size="16" class="text-ag-teal" />
                {{ $r['product_name'] }}
                @if ($latest)
                    <x-badge variant="success">Latest</x-badge>
                @endif
                @if ($r['type'])
                    <x-badge :variant="$typeVariant($r['type'])">{{ $types[$r['type']] ?? $r['type'] }}</x-badge>
                @endif
            </div>
            <h1 class="mt-3 text-[34px] font-medium leading-tight tracking-heading text-ag-ink sm:text-[42px]">
                v{{ $r['version'] }}@if ($r['title'])<span class="text-ag-subtle"> — {{ $r['title'] }}</span>@endif
            </h1>
            @if ($r['released_at'])
                <p class="mt-2 inline-flex items-center gap-1.5 font-mono text-sm text-ag-subtle">
                    <x-glyph name="calendar" :size="14" />
                    Released <time datetime="{{ $r['released_at'] }}">{{ Carbon::parse($r['released_at'])->format('d M Y') }}</time>
                </p>
            @endif
            @if ($r['summary'])
                <p class="mt-5 text-lg leading-relaxed text-ag-ink">{{ $r['summary'] }}</p>
            @endif
        </header>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_280px]">
            <article class="rounded-card bg-white p-6 shadow-ag sm:p-8">
                <h2 class="font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">What changed</h2>
                @if ($r['notes'])
                    <x-markdown :source="$r['notes']" class="mt-4" />
                @else
                    <p class="mt-4 text-sm text-ag-subtle">Maintenance release.</p>
                @endif
            </article>

            <aside class="space-y-4">
                <div class="rounded-card bg-white p-5 shadow-ag">
                    <div class="font-medium text-ag-ink">{{ $latest ? 'Get this version' : 'Update to the latest' }}</div>
                    <p class="mt-1 text-sm text-ag-subtle">
                        {{ $latest ? 'Install or update '.$r['product_name'].' step by step.' : 'A newer '.$r['product_name'].' is available.' }}
                    </p>
                    <x-button :href="route('dashboard.install', ['product' => $r['product']])" class="mt-4 w-full">
                        Install or update <x-glyph name="arrow-right" />
                    </x-button>
                </div>

                @if ($newer || $older)
                    <nav class="rounded-card bg-white p-5 text-sm shadow-ag" aria-label="Other versions">
                        <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">Other versions</div>
                        @if ($newer)
                            <a href="{{ Downloads::releaseUrl($r['product'], $newer['version']) }}" class="mt-3 flex items-center justify-between gap-2 text-ag-ink hover:text-ag-teal-text">
                                <span>Newer: v{{ $newer['version'] }}</span> <x-glyph name="arrow-right" :size="14" />
                            </a>
                        @endif
                        @if ($older)
                            <a href="{{ Downloads::releaseUrl($r['product'], $older['version']) }}" class="mt-3 flex items-center justify-between gap-2 text-ag-ink hover:text-ag-teal-text">
                                <span>Older: v{{ $older['version'] }}</span> <x-glyph name="arrow-left" :size="14" />
                            </a>
                        @endif
                    </nav>
                @endif
            </aside>
        </div>

        @if (! empty($r['checksum']))
            <section class="mt-6 rounded-card bg-white p-6 shadow-ag sm:p-8" aria-labelledby="verify">
                <h2 id="verify" class="flex items-center gap-2 text-lg font-medium text-ag-ink">
                    <x-glyph name="shield-check" :size="18" class="text-ag-teal" /> Verify your download
                </h2>
                <p class="mt-1 text-sm text-ag-subtle">
                    SHA-256 of @if ($product['file_name'])<span class="font-mono">{{ $product['file_name'] }}</span>@else the package @endif. Run the command where you downloaded it; it prints <span class="font-mono">OK</span> when the file is genuine.
                </p>
                <code class="mt-4 block break-all rounded-lg bg-ag-surface px-3 py-2 font-mono text-xs text-ag-ink">{{ $r['checksum'] }}</code>
                @if (! empty($r['validate_command']))
                    <x-code-block :code="$r['validate_command']" title="Linux" class="mt-4" />
                @endif
                @if (! empty($r['validate_command_windows']))
                    <x-code-block :code="$r['validate_command_windows']" title="Windows (PowerShell)" class="mt-3" />
                @endif
            </section>
        @endif
    </div>
</x-layouts.site>
