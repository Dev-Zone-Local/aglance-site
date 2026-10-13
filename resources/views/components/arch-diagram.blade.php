@props(['src', 'alt', 'caption' => null])
<figure {{ $attributes->class('relative overflow-hidden rounded-card bg-white shadow-ag') }}>
    <img src="{{ $src }}" alt="{{ $alt }}" class="block h-auto w-full" loading="lazy">
    @if ($caption)
        <figcaption class="border-t border-ag-line bg-ag-surface px-5 py-3 font-mono text-xs uppercase tracking-[0.12em] text-ag-subtle">{{ $caption }}</figcaption>
    @endif
</figure>
