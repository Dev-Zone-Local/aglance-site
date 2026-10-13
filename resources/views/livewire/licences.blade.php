<div>
    <x-page-header eyebrow="Licences" title="Management Console licences">
        Each licence activates one Management Console. Create a key, enter the emailed code, then paste the key into the installer.
    </x-page-header>

    <x-card class="p-7">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-medium text-ag-teal-text"><x-glyph name="key-round" :size="14" /> Management Console licences</div>
            <div class="font-mono text-xs text-ag-subtle">{{ $user->planLabel() }} plan · {{ $licences->count() }} / {{ $limit ?? 'unlimited' }} used</div>
        </div>
        <h2 class="mb-1 text-2xl font-medium text-ag-ink">Licence for the Management Console</h2>
        <p class="mb-6 max-w-2xl text-sm text-ag-subtle">
            The Management Console installer asks for a licence key to confirm the installation belongs to your account.
            Create one here and paste it when the installer prompts for it. Licences are valid for one year, or until you revoke them.
        </p>

        {{-- Key on screen after creating (or "Show key") --}}
        @if ($shownKey)
            <div class="mb-6 rounded-input bg-ag-warning-soft p-5" wire:key="key-{{ $shownKey['id'] }}">
                <div class="mb-3 flex items-start gap-2 text-sm text-ag-warning-text">
                    <x-glyph name="alert-triangle" class="mt-0.5 shrink-0" />
                    <span>
                        Copy the licence key for <b>{{ $shownKey['name'] }}</b> and keep it safe.
                        @if ($shownKey['visible_until'])
                            You can view it again from the list until {{ \Illuminate\Support\Carbon::parse($shownKey['visible_until'])->timezone(config('app.timezone'))->format('H:i') }}
                            (30 minutes after creation). After that it cannot be shown again; if you lose it, revoke the licence and create a new one.
                        @else
                            If you lose it, revoke the licence and create a new one.
                        @endif
                    </span>
                </div>
                @if ($shownKey['needs_approval'])
                    <p class="mb-3 text-sm text-ag-warning-text">After you enter the emailed code, an AtGlance admin must also approve this licence before the Management Console can use it. We will email you when it is approved.</p>
                @endif
                <x-code-block title="licence key" :code="$shownKey['key']" />
                <button type="button" wire:click="dismissKey" class="mt-4 text-sm font-medium text-ag-warning-text hover:underline">I've saved it</button>
            </div>
        @endif

        {{-- Enter the emailed code / create a licence --}}
        @if ($pending)
            <form wire:submit="confirm" class="mb-6 rounded-input bg-ag-surface p-5">
                <p class="mb-1 text-sm text-ag-ink">Activate licence <b class="font-mono">{{ $pending->name }}</b> — it works once you enter the emailed code.</p>
                <p class="mb-3 text-sm text-ag-subtle">{{ $pendingMessage }} The code expires in 10 minutes.</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <div>
                        <label for="licence-code" class="sr-only">5-digit code</label>
                        <input id="licence-code" wire:model="code" required inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="one-time-code" autofocus placeholder="5-digit code"
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 5)"
                            class="ag-input bg-white font-mono text-lg tracking-[0.4em] placeholder:text-sm placeholder:tracking-normal sm:w-44" @error('code') aria-invalid="true" @enderror>
                        @error('code') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                    </div>
                    <x-button wire:loading.attr="disabled" wire:target="confirm">Activate</x-button>
                    <button type="button" wire:click="resendCode" wire:loading.attr="disabled" class="px-2 py-2.5 text-sm font-medium text-ag-subtle hover:text-ag-ink">Resend code</button>
                </div>
            </form>
        @elseif ($atLimit)
            <div class="mb-6 rounded-input bg-ag-surface px-4 py-3 text-sm text-ag-subtle">
                Your {{ $user->planLabel() }} plan includes {{ $limit }} {{ $limit === 1 ? 'licence' : 'licences' }}.
                Revoke the existing one to create a new licence, or <a href="{{ route('pricing') }}" class="ag-link">upgrade your plan</a>.
            </div>
        @else
            <form wire:submit="create" class="mb-6">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <label for="licence-name" class="sr-only">Licence name</label>
                    <input id="licence-name" wire:model="name" required maxlength="100" placeholder="Licence name, e.g. prod-console" class="ag-input flex-1" @error('name') aria-invalid="true" @enderror>
                    <x-button wire:loading.attr="disabled" wire:target="create"><x-glyph name="plus" :size="14" /> Create licence</x-button>
                </div>
                @error('name') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
            </form>
        @endif

        {{-- Revoke --}}
        @if ($revoking)
            <form wire:submit="revoke" class="mb-6 rounded-input bg-ag-danger-soft p-5">
                <p class="mb-1 text-sm text-ag-ink">Revoke licence <b class="font-mono">{{ $revoking->name }}</b>?</p>
                <p class="mb-4 text-sm text-ag-danger-text">
                    Consoles installed with it can no longer be verified. This cannot be undone.
                    {{ $revokeMode === 'password' ? 'Enter your password to confirm.' : 'Enter the 5-digit code we email you to confirm.' }}
                </p>

                @if ($revokeMode === 'password')
                    <label for="revoke-password" class="sr-only">Your password</label>
                    <input id="revoke-password" type="password" wire:model="revokePassword" autofocus autocomplete="current-password" placeholder="Your password" class="ag-input bg-white sm:w-72">
                    @error('revokePassword') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                @elseif ($revokeCodeSent)
                    <p class="mb-2 text-sm text-ag-ink">{{ $revokeCodeSent }} The code expires in 10 minutes.</p>
                    <label for="revoke-code" class="sr-only">5-digit code</label>
                    <input id="revoke-code" wire:model="revokeCode" inputmode="numeric" maxlength="5" autocomplete="one-time-code" autofocus placeholder="5-digit code"
                        x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 5)"
                        class="ag-input bg-white font-mono text-lg tracking-[0.4em] placeholder:text-sm placeholder:tracking-normal sm:w-44">
                    @error('revokeCode') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                @else
                    <x-button type="button" variant="white" wire:click="sendRevokeCode" wire:loading.attr="disabled">Email me a code</x-button>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($revokeMode === 'password' || $revokeCodeSent)
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-full bg-ag-danger px-4 py-2 text-sm font-medium text-white hover:bg-ag-danger-text disabled:opacity-50">
                            <x-glyph name="trash" :size="14" /> Revoke licence
                        </button>
                    @endif
                    @if ($revokeMode === 'password')
                        <button type="button" wire:click="sendRevokeCode" class="text-sm text-ag-subtle hover:text-ag-ink">Email me a code instead</button>
                    @else
                        @if ($revokeCodeSent)
                            <button type="button" wire:click="sendRevokeCode" class="text-sm text-ag-subtle hover:text-ag-ink">Resend code</button>
                        @endif
                        @if ($user->password)
                            <button type="button" wire:click="usePassword" class="text-sm text-ag-subtle hover:text-ag-ink">Use password instead</button>
                        @endif
                    @endif
                    <button type="button" wire:click="cancelRevoke" class="text-sm text-ag-subtle hover:text-ag-ink">Cancel</button>
                </div>
            </form>
        @endif

        {{-- List --}}
        @if ($licences->isEmpty())
            <p class="text-sm text-ag-subtle">No licences yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ag-line text-left text-[11px] uppercase tracking-[0.14em] text-ag-subtle">
                            <th scope="col" class="py-2 pr-4 font-medium">Name</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Status</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Organization</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Created</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Expires</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Last seen</th>
                            <th scope="col" class="py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licences as $licence)
                            @php($status = $licence->status())
                            <tr class="border-b border-ag-line text-ag-ink" wire:key="licence-{{ $licence->id }}">
                                <td class="py-2.5 pr-4 font-mono">{{ $licence->name }}</td>
                                <td class="py-2.5 pr-4"><x-licence-status :status="$status" /></td>
                                <td class="py-2.5 pr-4 text-ag-subtle">
                                    @if ($licence->activation)
                                        <span title="In use since {{ $licence->activation->activated_at?->format('M j, Y') }}">
                                            <span class="text-ag-ink">{{ $licence->activation->org_name ?: '—' }}</span>
                                            @if ($licence->activation->console_version)<span class="text-ag-muted"> · v{{ $licence->activation->console_version }}</span>@endif
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4">{{ $licence->created_at?->format('M j, Y') ?? '—' }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-danger-text' => $status === 'expired'])>{{ $licence->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                                <td class="py-2.5 pr-4" title="{{ $licence->activation?->last_seen_at?->toDayDateTimeString() }}">{{ $licence->activation?->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="whitespace-nowrap py-2.5 text-right">
                                    @unless ($licence->isConfirmed())
                                        <button type="button" wire:click="enterCode({{ $licence->id }})" class="mr-4 inline-flex items-center gap-1.5 text-xs font-medium text-ag-teal-text hover:underline"><x-glyph name="key-round" :size="13" /> Enter code</button>
                                    @endunless
                                    @if ($licence->keyIsVisible())
                                        <button type="button" wire:click="showKey({{ $licence->id }})" title="Viewable until {{ $licence->key_visible_until->format('H:i') }}" class="mr-4 inline-flex items-center gap-1.5 text-xs font-medium text-ag-teal-text hover:underline"><x-glyph name="eye" :size="13" /> Show key</button>
                                    @endif
                                    <button type="button" wire:click="startRevoke({{ $licence->id }})" class="inline-flex items-center gap-1.5 text-xs font-medium text-ag-subtle hover:text-ag-danger-text"><x-glyph name="trash" :size="13" /> Revoke</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
