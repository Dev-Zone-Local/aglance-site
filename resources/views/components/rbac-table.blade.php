@php
    $rows = [
        ['Tenancy & billing', true, false, false],
        ['Manage organisations', true, true, false],
        ['Manage users & roles', true, true, false],
        ['Register / deregister systems', true, true, false],
        ['View configuration backups', true, true, true],
        ['Trigger reactivation', true, true, false],
        ['Read assigned workspaces only', false, false, true],
        ['Generate PAT tokens', true, true, true],
    ];
@endphp
<div {{ $attributes->class('overflow-x-auto rounded-card bg-white shadow-ag') }}>
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-ag-surface">
                <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">Capability</th>
                <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-[0.12em] text-ag-teal-text">superadmin</th>
                <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">admin</th>
                <th scope="col" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">user</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as [$label, $super, $admin, $user])
                <tr class="border-t border-ag-line">
                    <th scope="row" class="px-5 py-3 text-left font-normal text-ag-ink">{{ $label }}</th>
                    @foreach ([$super, $admin, $user] as $allowed)
                        <td class="text-center">
                            @if ($allowed)
                                <x-glyph name="check" :size="16" class="inline text-ag-success" /><span class="sr-only">Yes</span>
                            @else
                                <x-glyph name="x" :size="16" class="inline text-ag-ink-soft" /><span class="sr-only">No</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
