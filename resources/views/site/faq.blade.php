<x-layouts.site title="FAQ">
    <div class="mx-auto max-w-4xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="FAQ" title="Frequently asked." large>
            The short answers. For longer ones, see the <a href="{{ route('docs') }}" class="ag-link">docs</a>.
        </x-page-header>

        <div class="mt-12 space-y-10">
            @forelse ($groups as $category => $items)
                <section>
                    <h2 class="mb-3 font-mono text-[11px] uppercase tracking-[0.2em] text-ag-subtle">{{ $category }}</h2>
                    <div class="divide-y divide-ag-line rounded-card bg-white shadow-ag">
                        @foreach ($items as $faq)
                            <details class="group px-5">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-left font-medium text-ag-ink marker:hidden [&::-webkit-details-marker]:hidden">
                                    {{ $faq->question }}
                                    <x-glyph name="chevron-down" class="shrink-0 text-ag-muted transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="pb-4 leading-relaxed text-ag-subtle">{{ $faq->answer }}</div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="text-ag-subtle">No questions yet.</p>
            @endforelse
        </div>
    </div>
</x-layouts.site>
