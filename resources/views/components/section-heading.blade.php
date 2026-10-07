@props(['eyebrow' => null, 'title', 'sub' => null, 'center' => false])
<div {{ $attributes->class(['max-w-3xl', 'mx-auto text-center' => $center]) }}>
    @if ($eyebrow)
        <div class="mb-2 text-xs font-medium text-ag-teal-text">{{ $eyebrow }}</div>
    @endif
    <h2 class="mb-3 text-[26px] font-medium tracking-heading text-ag-ink sm:text-[30px]">{{ $title }}</h2>
    @if ($sub)
        <p class="text-[15px] leading-relaxed text-ag-subtle">{{ $sub }}</p>
    @endif
</div>
