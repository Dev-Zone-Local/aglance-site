{{-- Dark ink hero with sky (top-right) and mint (bottom-left) glows. Slots: eyebrow, title, actions, aside. --}}
@props(['tag' => 'h1'])
<section {{ $attributes->class('ag-hero px-6 py-8 sm:px-10 sm:py-12') }}>
    <div @class(['grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-center' => isset($aside)])>
        <div>
            @isset($eyebrow)
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-ag-mint">{{ $eyebrow }}</div>
            @endisset
            <{{ $tag }} class="mb-4 text-[30px] font-medium leading-tight tracking-heading text-white sm:text-[40px]">{{ $title }}</{{ $tag }}>
            @if (trim($slot) !== '')
                <div class="max-w-[640px] text-base leading-[1.7] text-ag-ink-soft">{{ $slot }}</div>
            @endif
            @isset($actions)
                <div class="mt-8 flex flex-wrap gap-3">{{ $actions }}</div>
            @endisset
        </div>
        @isset($aside)
            <div class="min-w-0">{{ $aside }}</div>
        @endisset
    </div>
</section>
