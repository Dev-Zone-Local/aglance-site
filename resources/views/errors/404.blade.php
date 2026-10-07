<x-layouts.site title="Page not found">
    <div class="flex min-h-[60vh] items-center justify-center">
        <div class="text-center">
            <div class="mb-3 font-mono text-7xl text-ag-teal-text">404</div>
            <p class="mb-6 text-ag-subtle">This page does not exist.</p>
            <x-button :href="route('home')" variant="ghost"><x-glyph name="arrow-left" :size="14" /> Back to the homepage</x-button>
        </div>
    </div>
</x-layouts.site>
