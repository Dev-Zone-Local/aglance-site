<x-layouts.site title="Known problems">
    <div class="mx-auto max-w-4xl px-1 py-10 sm:px-4"
        x-data="{ q: '', match(el) { const t = this.q.trim().toLowerCase(); return ! t || el.dataset.search.includes(t) } }">
        <x-page-header eyebrow="Troubleshooting" title="Known problems and fixes" large>
            Common problems with the AtGlance CLI and the Management Console, and how to solve them.
        </x-page-header>

        <div class="mt-8 flex flex-wrap items-center gap-2">
            <nav class="flex flex-wrap gap-2" aria-label="Filter by product">
                @foreach (['' => 'All'] + $products as $key => $label)
                    <a href="{{ route('known-problems', $key ? ['product' => $key] : []) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm transition-colors',
                            'bg-ag-ink text-white' => $product === ($key ?: null),
                            'bg-white text-ag-ink shadow-ag hover:text-ag-teal-text' => $product !== ($key ?: null),
                        ])
                        @if ($product === ($key ?: null)) aria-current="page" @endif>
                        {{ $label }} <span class="ml-1 text-xs opacity-60">{{ $key ? ($counts[$key] ?? 0) : array_sum($counts) }}</span>
                    </a>
                @endforeach
            </nav>
            <label class="relative ml-auto w-full sm:w-64">
                <span class="sr-only">Search problems</span>
                <input type="search" x-model="q" placeholder="Search problems…"
                    class="w-full rounded-full border-0 bg-white px-4 py-2 text-sm text-ag-ink shadow-ag placeholder:text-ag-muted focus:outline-none focus:ring-2 focus:ring-ag-teal">
            </label>
        </div>

        <div class="mt-8 divide-y divide-ag-line overflow-hidden rounded-card bg-white shadow-ag">
            @forelse ($issues as $issue)
                <details id="{{ $issue->slug }}" class="group scroll-mt-24 px-5 sm:px-6"
                    data-search="{{ \Illuminate\Support\Str::lower($issue->title.' '.$issue->symptom.' '.implode(' ', $issue->tags ?? []).' '.$issue->productLabel()) }}"
                    x-show="match($el)">
                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 py-5 text-left [&::-webkit-details-marker]:hidden">
                        <span>
                            <span class="mb-2 flex flex-wrap gap-1.5">
                                <x-badge :variant="$issue->product === 'cli' ? 'info' : 'success'">
                                    <x-glyph :name="$issue->product === 'cli' ? 'terminal' : 'server'" :size="11" /> {{ $issue->productLabel() }}
                                </x-badge>
                                @foreach ($issue->platformLabels() as $p)
                                    <x-badge>{{ $p }}</x-badge>
                                @endforeach
                                @foreach ($issue->tags ?? [] as $tag)
                                    <x-badge class="bg-transparent ring-1 ring-ag-line">#{{ $tag }}</x-badge>
                                @endforeach
                            </span>
                            <span class="block font-medium text-ag-ink">{{ $issue->title }}</span>
                        </span>
                        <x-glyph name="chevron-down" class="mt-1 shrink-0 text-ag-muted transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="space-y-4 pb-6 text-sm leading-relaxed text-ag-subtle">
                        @if (filled($issue->symptom))
                            <p class="rounded-xl bg-ag-surface px-4 py-3"><span class="font-medium text-ag-ink">What you see:</span> {{ $issue->symptom }}</p>
                        @endif
                        <x-markdown :source="$issue->solution" />
                        <a href="#{{ $issue->slug }}" class="inline-flex items-center gap-1.5 text-xs text-ag-teal-text hover:underline"><x-glyph name="copy" :size="12" /> Link to this problem</a>
                    </div>
                </details>
            @empty
                <p class="p-6 text-center text-sm text-ag-subtle">No known problems listed{{ $product ? ' for '.$products[$product] : '' }}.</p>
            @endforelse
        </div>

        <x-card flat class="mt-8 flex flex-wrap items-center gap-4 p-6">
            <div class="flex-1 text-sm text-ag-subtle">Problem not listed? Tell us what you see and we will help.</div>
            <x-button :href="route('contact', array_filter(['topic' => 'support', 'product' => $product])).'#contact-form'" variant="white">Contact support</x-button>
            <x-button :href="route('docs')" variant="white">Docs</x-button>
        </x-card>
    </div>

    {{-- Open the problem a link points to. --}}
    <script>
        (() => {
            const open = () => { const el = location.hash && document.getElementById(location.hash.slice(1)); if (el && el.tagName === 'DETAILS') el.open = true; };
            open(); window.addEventListener('hashchange', open);
        })();
    </script>
</x-layouts.site>
