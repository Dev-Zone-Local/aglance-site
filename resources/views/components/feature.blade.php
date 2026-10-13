{{-- Feature card: ink icon tile, title, short text. --}}
@props(['icon' => null, 'title'])
<div {{ $attributes->class('lift relative rounded-card bg-white p-[22px] shadow-ag') }}>
    @if ($icon)
        <span class="mb-3.5 inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint">
            <x-glyph :name="$icon" :size="16" />
        </span>
    @endif
    <h3 class="mb-1.5 text-[15px] font-semibold tracking-normal text-ag-ink">{{ $title }}</h3>
    <p class="text-[13px] leading-[1.6] text-ag-subtle">{{ $slot }}</p>
</div>
