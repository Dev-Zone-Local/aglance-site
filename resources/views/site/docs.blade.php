<x-layouts.site title="Docs">
    <div class="mx-auto grid max-w-7xl gap-10 px-1 py-10 sm:px-4 lg:grid-cols-[240px_1fr]">
        <x-docs-sidebar :docs="$docs" />
        <div>
            <x-page-header eyebrow="Documentation" title="Build, deploy, operate." large>
                Everything you need to install the CLI, deploy the Console, and run AtGlance in production.
            </x-page-header>
            <div class="mt-12 space-y-10">
                @foreach ($groups as $section => $items)
                    <section>
                        <h2 class="mb-3 font-mono text-[11px] uppercase tracking-[0.2em] text-ag-subtle">{{ $section }}</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($items as $doc)
                                <a href="{{ route('docs.show', $doc->slug) }}" class="group lift rounded-card bg-white p-5 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                                    <x-glyph name="book-open" class="mb-3 text-ag-teal" />
                                    <div class="mb-1 font-medium text-ag-ink">{{ $doc->title }}</div>
                                    <div class="inline-flex items-center gap-1.5 text-xs text-ag-subtle transition-all group-hover:gap-2.5">Read <x-glyph name="arrow-right" :size="12" /></div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.site>
