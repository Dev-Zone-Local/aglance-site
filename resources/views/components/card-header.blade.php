{{-- Card header: 28px ink icon tile + 15px title. --}}
@props(['icon' => null, 'title'])
<div class="mb-4 flex items-center gap-2.5">
    @if ($icon)
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-ag-ink text-white">
            <x-glyph :name="$icon" :size="13" />
        </span>
    @endif
    <h2 class="text-[15px] font-medium tracking-normal text-ag-ink">{{ $title }}</h2>
    @isset($action)
        <div class="ml-auto">{{ $action }}</div>
    @endisset
</div>
