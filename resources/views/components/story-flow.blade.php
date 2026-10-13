{{-- Numbered steps in a row. :steps="[['title' => ..., 'desc' => ...], ...]" --}}
@props(['steps' => []])
<div {{ $attributes->class('rounded-card bg-white p-6 shadow-ag sm:p-8') }}>
    <ol class="grid grid-cols-1 gap-3 md:grid-cols-4 md:gap-2">
        @foreach ($steps as $i => $step)
            <li class="relative">
                <div class="lift h-full rounded-input bg-ag-surface p-4">
                    <div class="mb-2 font-mono text-[10px] text-ag-teal-text">STEP {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="mb-1 text-sm font-semibold text-ag-ink">{{ $step['title'] }}</div>
                    <div class="text-xs leading-relaxed text-ag-subtle">{{ $step['desc'] }}</div>
                </div>
                @unless ($loop->last)
                    <x-glyph name="arrow-right" :size="14" class="absolute -right-1.5 top-1/2 z-10 hidden -translate-y-1/2 rounded-full bg-white text-ag-teal md:block" />
                @endunless
            </li>
        @endforeach
    </ol>
</div>
