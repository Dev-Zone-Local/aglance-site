<x-layouts.site :title="$doc->title">
    <div class="mx-auto grid max-w-7xl gap-10 px-1 py-10 sm:px-4 lg:grid-cols-[240px_1fr]">
        <x-docs-sidebar :docs="$docs" :current="$doc->slug" />
        <article class="min-w-0 max-w-3xl">
            <div class="mb-3 text-xs font-medium text-ag-teal-text">{{ $doc->section }}</div>
            <x-markdown :source="$doc->content" />
        </article>
    </div>
</x-layouts.site>
