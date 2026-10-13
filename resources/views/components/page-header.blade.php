{{-- Title block at the top of inner pages. --}}
@props(['eyebrow' => null, 'title', 'large' => false])
<div {{ $attributes->class('mb-6') }}>
    @if ($eyebrow)
        <div class="mb-2 text-xs font-medium text-ag-teal-text">{{ $eyebrow }}</div>
    @endif
    <h1 @class([
        'font-medium tracking-heading text-ag-ink',
        'text-[30px]' => ! $large,
        'mb-2 max-w-4xl text-4xl leading-[1.05] sm:text-5xl lg:text-6xl' => $large,
    ])>{{ $title }}</h1>
    @if (trim($slot) !== '')
        <p @class(['mt-2 max-w-3xl leading-relaxed text-ag-subtle', 'text-[15px]' => ! $large, 'mt-4 text-lg' => $large])>{{ $slot }}</p>
    @endif
</div>
