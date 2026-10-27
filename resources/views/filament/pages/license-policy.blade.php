<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Defaults for new licence files. A plan default overrides the global default; a user's custom license
        (Users → Create Custom License) overrides both. Saving changes only licences generated afterwards:
        files already issued keep their limits until they expire, and cannot be revoked offline.
    </p>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">Save policy</x-filament::button>
        </div>
    </form>

    <x-filament::section heading="Change history" description="Every change to the license policies, custom licenses and the feature catalog.">
        @php($history = $this->history())
        @if ($history->isEmpty())
            <p class="text-sm text-gray-500">No changes yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" data-testid="policy-history">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-white/10">
                            <th class="py-2 pr-4">When</th>
                            <th class="py-2 pr-4">Admin</th>
                            <th class="py-2 pr-4">Policy</th>
                            <th class="py-2 pr-4">Change</th>
                            <th class="py-2 pr-4">Old</th>
                            <th class="py-2">New</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr class="border-b border-gray-100 align-top dark:border-white/5">
                                <td class="whitespace-nowrap py-2 pr-4">{{ $row->created_at->format('M j, Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $row->admin?->email ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $row->subject_label }}</td>
                                <td class="py-2 pr-4">{{ ucfirst($row->action) }}{{ $row->field ? ': '.str_replace('_', ' ', $row->field) : '' }}</td>
                                <td class="py-2 pr-4 text-gray-500">{{ $row->old_value ?? '—' }}</td>
                                <td class="py-2">{{ $row->new_value ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
