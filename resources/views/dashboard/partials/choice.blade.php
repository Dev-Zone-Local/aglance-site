{{--
    Big clickable choice card for the install wizard. $href null = disabled ("Coming soon").
    Optional $extra = ['href' => ..., 'label' => ..., 'icon' => ...]: a second link on the right of
    "Continue". The whole card stays clickable (stretched link), the extra link sits above it.
--}}
@php($extra = $extra ?? null)
<div @class(['group relative h-full rounded-card', 'focus-within:outline focus-within:outline-2 focus-within:outline-ag-teal' => $href]) @unless ($href) aria-disabled="true" @endunless>
    <x-card :hover="(bool) $href" @class(['relative flex h-full flex-col p-6', 'opacity-60' => ! $href])>
        @if ($badge)
            <x-badge :variant="$badgeVariant" class="absolute right-5 top-5">{{ $badge }}</x-badge>
        @endif
        <span @class(['mb-4 inline-flex h-11 w-11 items-center justify-center rounded-[12px]', 'bg-ag-ink text-ag-mint' => $href, 'bg-ag-surface text-ag-subtle' => ! $href])>
            <x-glyph :name="$icon" :size="20" />
        </span>
        <h2 class="text-[17px] font-medium tracking-heading text-ag-ink">{{ $title }}</h2>
        <p class="mt-1 text-sm text-ag-subtle">{{ $hint }}</p>
        @if ($href)
            <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-4">
                <a href="{{ $href }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text outline-none after:absolute after:inset-0 after:rounded-card after:content-['']">
                    Continue <x-glyph name="arrow-right" :size="14" />
                </a>
                @if ($extra)
                    <a href="{{ $extra['href'] }}" class="relative z-10 inline-flex items-center gap-1.5 rounded-full bg-ag-surface px-3 py-1.5 text-xs font-medium text-ag-ink transition-colors hover:bg-ag-success-soft hover:text-ag-success-text">
                        <x-glyph :name="$extra['icon'] ?? 'alert-triangle'" :size="13" /> {{ $extra['label'] }}
                    </a>
                @endif
            </div>
        @endif
    </x-card>
</div>
