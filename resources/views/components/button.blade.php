{{-- Pill button. With href it renders a link, otherwise a <button>. --}}
@props(['variant' => 'primary', 'size' => null, 'href' => null, 'type' => 'submit'])
@php
    $variants = [
        'primary' => 'bg-ag-gradient text-ag-ink hover:bg-ag-gradient-hover hover:shadow-ag-glow',
        'dark' => 'bg-ag-ink text-white hover:bg-ag-ink-hover',
        'ghost' => 'bg-ag-surface text-ag-ink hover:bg-ag-line',
        'danger' => 'bg-ag-danger-soft text-ag-danger-text hover:bg-ag-danger-hover',
        'white' => 'bg-white text-ag-ink shadow-ag hover:shadow-ag-strong',
    ];
    $sizes = ['sm' => 'px-3.5 py-[7px] text-[13px]', 'lg' => 'px-6 py-3 text-[15px]'];
    $class = 'inline-flex items-center justify-center gap-2 rounded-full font-medium whitespace-nowrap transition duration-200 '
        .'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ag-teal '
        .'disabled:opacity-50 disabled:pointer-events-none '
        .($sizes[$size] ?? 'px-[18px] py-2.5 text-sm').' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif
