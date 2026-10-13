{{-- Docs navigation, grouped by section (from the CMS). --}}
@props(['docs', 'current' => null])
<aside class="overflow-y-auto lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)]">
    <div class="mb-4 px-1 text-xs font-medium text-ag-teal-text">Documentation</div>
    <nav class="space-y-6" aria-label="Documentation">
        @foreach ($docs->groupBy('section') as $section => $items)
            <div>
                <div class="mb-2 px-1 font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">{{ $section }}</div>
                <ul class="space-y-1">
                    @foreach ($items as $doc)
                        <li>
                            <a href="{{ route('docs.show', $doc->slug) }}" @if ($current === $doc->slug) aria-current="page" @endif @class([
                                'block rounded-md border-l-2 px-3 py-1.5 text-[13px] transition-colors',
                                'border-ag-teal bg-ag-info-soft font-medium text-ag-ink' => $current === $doc->slug,
                                'border-transparent text-ag-subtle hover:text-ag-ink' => $current !== $doc->slug,
                            ])>{{ $doc->title }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
