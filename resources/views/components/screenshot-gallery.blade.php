{{-- Gallery screenshots from Admin → Product pages ([{image, title, caption}]). Click one to view it full size. --}}
@props(['screenshots'])
@php
    $items = collect($screenshots)->filter(fn ($s) => filled($s['image'] ?? null))
        ->map(fn ($s) => ['src' => \App\Support\ProductShowcase::imageUrl($s['image']), 'title' => $s['title'] ?? '', 'caption' => $s['caption'] ?? null])
        ->values();
@endphp
<div {{ $attributes }}
    x-data="{ items: @js($items), open: null,
        show(i) { this.open = i }, close() { this.open = null },
        next() { this.open = (this.open + 1) % this.items.length },
        prev() { this.open = (this.open - 1 + this.items.length) % this.items.length } }"
    x-on:keydown.escape.window="close()"
    x-on:keydown.arrow-right.window="open !== null && next()"
    x-on:keydown.arrow-left.window="open !== null && prev()">

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $i => $shot)
            <figure class="lift overflow-hidden rounded-card bg-white shadow-ag">
                <button type="button" x-on:click="show({{ $i }})" class="block w-full bg-ag-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal"
                    aria-label="View {{ $shot['title'] }} full size">
                    <img src="{{ $shot['src'] }}" alt="{{ $shot['title'] }}" loading="lazy" class="aspect-[16/10] w-full object-contain">
                </button>
                <figcaption class="p-4">
                    <div class="font-medium text-ag-ink">{{ $shot['title'] }}</div>
                    @if ($shot['caption'])
                        <div class="mt-1 text-sm leading-relaxed text-ag-subtle">{{ $shot['caption'] }}</div>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>

    <template x-teleport="body">
        <div x-show="open !== null" x-cloak x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-4 sm:p-8"
            role="dialog" aria-modal="true" x-on:click.self="close()">
            <button type="button" x-on:click="close()" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Close">
                <x-glyph name="x" :size="20" />
            </button>
            <template x-if="items.length > 1">
                <div>
                    <button type="button" x-on:click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Previous screenshot">
                        <x-glyph name="arrow-left" :size="20" />
                    </button>
                    <button type="button" x-on:click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Next screenshot">
                        <x-glyph name="arrow-right" :size="20" />
                    </button>
                </div>
            </template>
            <figure class="max-h-full max-w-6xl text-center" x-show="open !== null">
                <img :src="open !== null ? items[open].src : ''" :alt="open !== null ? items[open].title : ''" class="mx-auto max-h-[80vh] w-auto rounded-lg shadow-2xl">
                <figcaption class="mt-3 text-white">
                    <span class="font-medium" x-text="open !== null ? items[open].title : ''"></span>
                    <span class="block text-sm text-white/70" x-text="open !== null ? (items[open].caption || '') : ''"></span>
                </figcaption>
            </figure>
        </div>
    </template>
</div>
