<x-layouts.site :title="$page->title">
    <div class="mx-auto max-w-3xl px-1 py-10 sm:px-4">
        <x-markdown :source="$page->content" />
    </div>
</x-layouts.site>
