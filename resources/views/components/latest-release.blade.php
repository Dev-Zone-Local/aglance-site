{{-- "Latest release" strip on product pages, linking to the release notes. --}}
@props(['product', 'release' => null])
@if ($release)
    <a href="{{ route('releases', ['product' => $product]) }}#{{ \App\Support\Downloads::anchor($product, $release['version']) }}"
        {{ $attributes->class('lift flex flex-wrap items-center gap-3 rounded-card bg-white px-5 py-4 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal') }}>
        <x-badge variant="success">v{{ $release['version'] }}</x-badge>
        <span class="text-sm text-ag-ink">
            <span class="font-medium">Latest release{{ ! empty($release['title']) ? ':' : '' }}</span>
            {{ $release['title'] ?? '' }}
            @if (! empty($release['summary']))
                <span class="block text-ag-subtle sm:inline">{{ $release['summary'] }}</span>
            @endif
        </span>
        <span class="ml-auto inline-flex items-center gap-1.5 text-sm text-ag-teal-text">Release notes <x-glyph name="arrow-right" :size="14" /></span>
    </a>
@endif
