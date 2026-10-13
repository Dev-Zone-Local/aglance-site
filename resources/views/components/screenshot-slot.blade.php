{{--
    Window frame for one showcase screenshot. Browser chrome for the console, terminal chrome for
    the CLI. With an image it shows it (click for full size). Without one it shows an empty frame;
    signed-in admins also see what to capture and where to upload it.
--}}
@props(['image' => null, 'alt' => '', 'caption' => null, 'path' => null, 'hint' => null, 'icon' => 'monitor', 'terminal' => false, 'dark' => false])
@php
    $isAdmin = auth()->user()?->isAdmin();
    $src = \App\Support\ProductShowcase::imageUrl($image);
    $bar = $terminal ? '$ '.($path ?: 'atglance') : 'https://atglance.internal'.($path ?: '/');
@endphp
<figure {{ $attributes->class('min-w-0') }} @if ($src) x-data="{ zoom: false }" x-on:keydown.escape.window="zoom = false" @endif>
    <div @class([
        'overflow-hidden rounded-[18px] ring-1',
        'bg-[#1b1f24] ring-white/10 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.6)]' => $dark || $terminal,
        'bg-white ring-black/5 shadow-ag-strong' => ! $dark && ! $terminal,
    ])>
        <div @class(['flex items-center gap-2 px-4 py-2.5', 'bg-white/5' => $dark || $terminal, 'bg-ag-surface' => ! $dark && ! $terminal])>
            <span class="h-2.5 w-2.5 rounded-full bg-[#ff5f57]"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-[#febc2e]"></span>
            <span class="h-2.5 w-2.5 rounded-full bg-[#28c840]"></span>
            <span @class([
                'ml-3 flex-1 truncate rounded-md px-3 py-1 font-mono text-[11px]',
                'bg-black/30 text-white/50' => $dark || $terminal,
                'bg-white text-ag-subtle' => ! $dark && ! $terminal,
            ])>{{ $bar }}</span>
        </div>

        @if ($src)
            <button type="button" x-on:click="zoom = true" @class(['block w-full cursor-zoom-in focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal', 'bg-[#14171b]' => $dark || $terminal, 'bg-white' => ! $dark && ! $terminal]) aria-label="View {{ $alt }} full size">
                {{-- Natural aspect ratio: the whole screenshot is visible, never cropped. --}}
                <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" class="mx-auto block h-auto max-h-[78vh] w-full object-contain">
            </button>
        @else
            <div @class([
                'relative flex aspect-[16/10] w-full flex-col items-center justify-center gap-3 p-6 text-center',
                'bg-gradient-to-br from-[#20262d] to-[#14171b]' => $dark || $terminal,
                'bg-gradient-to-br from-ag-surface to-white' => ! $dark && ! $terminal,
            ])>
                @if ($isAdmin)
                    <div class="absolute inset-3 rounded-xl border-2 border-dashed border-ag-teal/50"></div>
                @endif
                <span @class(['inline-flex h-14 w-14 items-center justify-center rounded-2xl', 'bg-white/10 text-ag-mint' => $dark || $terminal, 'bg-white text-ag-teal shadow-ag' => ! $dark && ! $terminal])>
                    <x-glyph :name="$icon" :size="26" />
                </span>
                @if ($isAdmin)
                    <div @class(['relative max-w-md text-xs leading-relaxed', 'text-white/70' => $dark || $terminal, 'text-ag-subtle' => ! $dark && ! $terminal])>
                        <div @class(['mb-1 text-sm font-medium', 'text-white' => $dark || $terminal, 'text-ag-ink' => ! $dark && ! $terminal])>Screenshot needed</div>
                        @if ($hint){{ $hint }}<br>@endif
                        Upload it in <a href="{{ url('/admin/product-pages') }}" class="underline">Admin → Product pages</a>. 1600×1000 px works best.
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($src)
        <template x-teleport="body">
            <div x-show="zoom" x-cloak x-transition.opacity.duration.150ms x-on:click="zoom = false"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4 sm:p-8" role="dialog" aria-modal="true">
                <img src="{{ $src }}" alt="{{ $alt }}" class="max-h-[90vh] w-auto rounded-lg shadow-2xl">
            </div>
        </template>
    @endif
    @if ($caption)
        <figcaption @class(['mt-3 text-center text-sm', 'text-white/60' => $dark, 'text-ag-subtle' => ! $dark])>{{ $caption }}</figcaption>
    @endif
</figure>
