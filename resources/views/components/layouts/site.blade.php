{{-- Public pages: one frosted-glass frame over the glowing page background. --}}
@props(['title' => null, 'description' => null])
<x-layouts.base :title="$title" :description="$description">
    <div class="ag-frame flex flex-col">
        <x-site-header />
        <main id="main" class="flex-1">
            {{ $slot }}
        </main>
        <x-site-footer />
    </div>
</x-layouts.base>
