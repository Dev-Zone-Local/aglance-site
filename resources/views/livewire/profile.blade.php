<div>
    <x-page-header eyebrow="Account" title="Profile" />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card class="p-6">
            <x-card-header icon="user-round" title="Your details" />
            <form wire:submit="saveName" class="space-y-4">
                <div>
                    <label for="profile-name" class="ag-label">Name</label>
                    <input id="profile-name" wire:model="name" required maxlength="255" class="ag-input" autocomplete="name">
                    @error('name') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <div class="ag-label">Email</div>
                    <div class="flex flex-wrap items-center gap-2 text-sm text-ag-ink">
                        {{ $user->email }}
                        @if ($user->hasVerifiedEmail())
                            <x-badge variant="success">Verified</x-badge>
                        @else
                            <x-badge variant="warning">Not verified</x-badge>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-badge>{{ $user->planLabel() }} plan</x-badge>
                    <x-badge>Signed in with {{ $user->auth_method === 'github' ? 'GitHub' : 'email' }}</x-badge>
                </div>
                <x-button wire:loading.attr="disabled" wire:target="saveName">Save</x-button>
            </form>
        </x-card>

        <x-card class="p-6">
            <x-card-header icon="key-round" :title="$user->password ? 'Change password' : 'Set a password'" />
            <form wire:submit="savePassword" class="space-y-4">
                @if ($user->password)
                    <div>
                        <label for="pw-current" class="ag-label">Current password</label>
                        <input id="pw-current" type="password" wire:model="current_password" autocomplete="current-password" required class="ag-input">
                        @error('current_password') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="pw-new" class="ag-label">New password</label>
                    <input id="pw-new" type="password" wire:model="password" autocomplete="new-password" required minlength="6" class="ag-input">
                    @error('password') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pw-confirm" class="ag-label">Confirm new password</label>
                    <input id="pw-confirm" type="password" wire:model="password_confirmation" autocomplete="new-password" required minlength="6" class="ag-input">
                </div>
                <x-button wire:loading.attr="disabled" wire:target="savePassword">Update password</x-button>
                <p class="text-xs text-ag-muted">Other devices are signed out after a password change.</p>
            </form>
        </x-card>

        <x-card class="p-6 lg:col-span-2">
            <x-card-header icon="bell" title="Email notifications" />
            <label class="flex cursor-pointer items-center gap-3">
                <input type="checkbox" wire:model.live="notifyUpdates" class="h-4 w-4 accent-[#2CB7D9]">
                <span class="text-sm text-ag-ink">Email me when a new CLI or Management Console version is released</span>
            </label>
        </x-card>
    </div>
</div>
