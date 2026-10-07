{{-- Licence status pill: soft background + strong text, with a short explanation on hover. --}}
@props(['status'])
@php
    $styles = [
        'unverified' => ['bg-ag-surface text-ag-subtle', 'bg-ag-muted', 'Pending', 'Enter the 5-digit code we emailed you to activate this licence.'],
        'under_review' => ['bg-ag-warning-soft text-ag-warning-text', 'bg-ag-warning', 'Under review', 'An AtGlance admin must approve this licence before the Management Console can use it.'],
        'ready' => ['bg-ag-info-soft text-ag-teal-text', 'bg-ag-info', 'Ready', 'Active. Enter the key in the Management Console installer.'],
        'in_use' => ['bg-ag-success-soft text-ag-success-text', 'bg-ag-success', 'In use', 'A Management Console is using this licence.'],
        'expired' => ['bg-ag-danger-soft text-ag-danger-text', 'bg-ag-danger', 'Expired', 'This licence has expired. Create a new licence for the Management Console.'],
    ];
    [$class, $dot, $label, $title] = $styles[$status] ?? $styles['unverified'];
@endphp
<span title="{{ $title }}" {{ $attributes->class('inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium '.$class) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span> {{ $label }}
</span>
