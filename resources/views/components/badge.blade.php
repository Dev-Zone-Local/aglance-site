@props(['variant' => 'default'])
@php
    $variants = [
        'default' => 'bg-ag-surface text-ag-subtle',
        'success' => 'bg-ag-success-soft text-ag-success-text',
        'danger' => 'bg-ag-danger-soft text-ag-danger-text',
        'warning' => 'bg-ag-warning-soft text-ag-warning-text',
        'info' => 'bg-ag-info-soft text-ag-teal-text',
    ];
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium '.($variants[$variant] ?? $variants['default'])) }}>{{ $slot }}</span>
