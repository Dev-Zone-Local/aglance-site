@props(['variant' => 'info', 'icon' => null])
@php
    $variants = [
        'success' => 'bg-ag-success-soft text-ag-success-text',
        'danger' => 'bg-ag-danger-soft text-ag-danger-text',
        'warning' => 'bg-ag-warning-soft text-ag-warning-text',
        'info' => 'bg-ag-info-soft text-ag-teal-text',
    ];
@endphp
<div {{ $attributes->class('flex items-start gap-2.5 rounded-input px-4 py-3 text-sm '.($variants[$variant] ?? $variants['info'])) }} role="{{ $variant === 'danger' ? 'alert' : 'status' }}">
    @if ($icon)
        <x-glyph :name="$icon" :size="16" class="mt-0.5 shrink-0" />
    @endif
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
