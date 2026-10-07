{{-- Big clickable choice card for the install wizard. $href null = disabled ("Coming soon"). --}}
@if ($href)
    <a href="{{ $href }}" class="block rounded-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
@else
    <div aria-disabled="true">
@endif
    <x-card :hover="(bool) $href" @class(['relative h-full p-6', 'opacity-60' => ! $href])>
        @if ($badge)
            <x-badge :variant="$badgeVariant" class="absolute right-5 top-5">{{ $badge }}</x-badge>
        @endif
        <span @class(['mb-4 inline-flex h-11 w-11 items-center justify-center rounded-[12px]', 'bg-ag-ink text-ag-mint' => $href, 'bg-ag-surface text-ag-subtle' => ! $href])>
            <x-glyph :name="$icon" :size="20" />
        </span>
        <h2 class="text-[17px] font-medium tracking-heading text-ag-ink">{{ $title }}</h2>
        <p class="mt-1 text-sm text-ag-subtle">{{ $hint }}</p>
        @if ($href)
            <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text">Continue <x-glyph name="arrow-right" :size="14" /></span>
        @endif
    </x-card>
@if ($href)
    </a>
@else
    </div>
@endif
