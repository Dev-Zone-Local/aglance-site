{{-- White card (default), dark ink card, or flat surface panel. No borders. --}}
@props(['dark' => false, 'flat' => false, 'hover' => false])
<div {{ $attributes->class([
    'rounded-card p-5',
    'ag-hero text-white' => $dark,
    'bg-ag-surface' => $flat && ! $dark,
    'bg-white shadow-ag' => ! $flat && ! $dark,
    'lift' => $hover,
]) }}>{{ $slot }}</div>
