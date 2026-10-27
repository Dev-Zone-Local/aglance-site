<div>
    <x-page-header eyebrow="Licences" title="Management Console licences">
        Each licence activates one Management Console: online with its licence key, or offline with a licence file.
    </x-page-header>

    <x-card class="p-7">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-medium text-ag-teal-text"><x-glyph name="key-round" :size="14" /> Management Console licences</div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="font-mono text-xs text-ag-subtle">{{ $user->planLabel() }} plan · {{ $licences->count() }} / {{ $limit }} licences used</div>
                <a href="{{ route('contact', ['topic' => 'more_licences']) }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-ag-teal-text hover:underline" data-testid="more-licences"><x-glyph name="plus" :size="13" /> Need more licences</a>
            </div>
        </div>
        <h2 class="mb-1 text-2xl font-medium text-ag-ink">Licence for the Management Console</h2>
        <p class="mb-3 max-w-2xl text-sm text-ag-subtle">
            Create a licence and confirm it with the code we email you. Then use it in the Management Console installer, or in
            Admin Settings &rsaquo; Licence, in one of two ways:
        </p>
        <ul class="mb-5 max-w-2xl space-y-1.5 text-sm text-ag-ink">
            <li class="flex items-start gap-2"><x-glyph name="key-round" :size="14" class="mt-0.5 shrink-0 text-ag-teal-text" /><span><b>Console with internet access:</b> paste the licence key. The console activates it online once, and its Installation ID is recorded automatically.</span></li>
            <li class="flex items-start gap-2"><x-glyph name="download" :size="14" class="mt-0.5 shrink-0 text-ag-teal-text" /><span><b>Console without internet access:</b> generate the licence file (.atgl) from the licence and upload it. The Installation ID is {{ $policy['install_id_mode'] === 'required' ? 'required' : 'optional' }} for your licences.</span></li>
        </ul>
        <p class="mb-5 max-w-2xl text-sm text-ag-subtle">Use a licence one way only. Once its key is activated, no licence file can be generated from it; once a licence file is generated, its key no longer works and is not shown again. Either way the licence is <b>in use</b> and cannot be revoked until it expires. The key and its file expire on the same date. Exception: when a newer licence file is generated for the same Installation ID, it replaces the older one on that console, and the older licence can be revoked.</p>

        <div class="mb-6 rounded-input bg-ag-surface p-5" data-testid="licence-policy">
            <div class="mb-3 flex items-center gap-2 text-sm text-ag-ink">
                Policy: <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-white text-ag-teal-text' => $policy['policy_type'] === 'custom', 'bg-white text-ag-subtle' => $policy['policy_type'] !== 'custom'])>{{ $policy['label'] }}</span>
                <span class="text-xs text-ag-muted">applies to the licence key and the licence file alike</span>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-5">
                <div><dt class="text-xs text-ag-muted">Licence limit</dt><dd class="text-ag-ink">{{ $policy['max_licences'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Node limit</dt><dd class="text-ag-ink">{{ $policy['max_nodes'] === 0 ? 'Unlimited' : $policy['max_nodes'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">User limit</dt><dd class="text-ag-ink">{{ $policy['max_users'] === 0 ? 'Unlimited' : $policy['max_users'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Valid for</dt><dd class="text-ag-ink">{{ $policy['expires_on'] ? 'until '.$policy['expires_on']->format('M j, Y') : $policy['validity_months'].' '.($policy['validity_months'] === 1 ? 'month' : 'months') }}</dd></div>
                <div><dt class="text-xs text-ag-muted">File install window</dt><dd class="text-ag-ink">{{ $policy['activation_window_minutes'] }} minutes</dd></div>
            </dl>
            <div class="mt-3 text-sm" data-testid="licence-premium">
                <span class="text-xs text-ag-muted">Premium features in your licence:</span>
                @if (in_array(\App\Models\FeatureCatalog::BUNDLE_PREMIUM, $policy['bundles'], true))
                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-0.5 text-xs text-ag-ink" data-testid="all-premium">Premium Features (all) <span class="rounded-full bg-amber-100 px-1.5 text-[10px] font-medium text-amber-800">Premium</span></span>
                @else
                @forelse ($policy['features'] as $feature)
                    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-0.5 text-xs text-ag-ink">{{ $featureNames[$feature] ?? $feature }} <span class="rounded-full bg-amber-100 px-1.5 text-[10px] font-medium text-amber-800">Premium</span></span>
                @empty
                    <span class="text-ag-subtle">None</span>
                @endforelse
                @endif
                <span class="block text-xs text-ag-muted mt-1">All other console features are free and always included.</span>
            </div>
        </div>

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
            <div class="mb-6 rounded-input bg-ag-surface px-4 py-3 text-sm text-ag-subtle" data-testid="licence-limit">
                {{ $limitMessage }}
                <a href="{{ route('contact', ['topic' => 'more_licences']) }}" class="ag-link">Need more licences? Contact AtGlance Support</a>
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

        {{-- Download the licence file of one licence (offline console) --}}
        @if ($fileFor)
            <form method="POST" action="{{ route('dashboard.licences.file', $fileFor->id) }}" class="mb-6 max-w-2xl rounded-input bg-ag-surface p-5" wire:key="file-form-{{ $fileFor->id }}">
                @csrf
                <p class="mb-1 text-sm text-ag-ink">Licence file for <b class="font-mono">{{ $fileFor->name }}</b></p>
                <p class="mb-3 text-sm text-ag-subtle">
                    For a Management Console without internet access. Its Installation ID is shown in the installer and in
                    Admin Settings &rsaquo; Licence.
                    @if ($policy['install_id_mode'] === 'required')
                        Enter it: the file works on that console only.
                    @else
                        It is optional: with it, the file works on that console only; without it, on any one console.
                    @endif
                    Upload the file within {{ $policy['activation_window_minutes'] }} minutes. After this the licence is in use until it expires.
                </p>
                <label for="install-id" class="mb-1.5 block text-sm font-medium text-ag-ink">Installation ID{{ $policy['install_id_mode'] === 'required' ? '' : ' (optional)' }}</label>
                {{-- Input and button on one row; the help text goes below the row so the button lines up with the input. --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <input id="install-id" name="install_id" value="{{ old('install_id', $fileFor->activation?->instance_id === $unbound ? '' : $fileFor->activation?->instance_id) }}" @required($policy['install_id_mode'] === 'required') maxlength="64" autocomplete="off" spellcheck="false"
                        placeholder="e.g. 3f2b8c1e-…" class="ag-input min-w-0 flex-1 bg-white font-mono text-sm" @error('install_id') aria-invalid="true" @enderror>
                    <x-button class="shrink-0"><x-glyph name="download" :size="16" /> Generate licence file</x-button>
                </div>
                @error('install_id') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                @error('license_file') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                <button type="button" wire:click="cancelFile" class="mt-3 text-sm text-ag-subtle hover:text-ag-ink">Cancel</button>
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
                            @php($replacedBy = \App\Support\LicenceManager::replacedBy($licence))
                            <tr class="border-b border-ag-line text-ag-ink" wire:key="licence-{{ $licence->id }}">
                                <td class="py-2.5 pr-4 font-mono">{{ $licence->name }}</td>
                                <td class="py-2.5 pr-4">
                                    <x-licence-status :status="$status" />
                                    @if ($licence->usedByFile())
                                        <span class="block text-xs text-ag-muted" data-testid="used-by">with licence file</span>
                                    @elseif ($licence->activation)
                                        <span class="block text-xs text-ag-muted" data-testid="used-by">with licence key</span>
                                    @endif
                                    @if ($replacedBy)
                                        <span class="block text-xs text-ag-warning-text" data-testid="replaced-by"
                                            title="A newer licence file for the same Installation ID replaced this one on the console">Replaced by {{ $replacedBy->name }} · can be revoked</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4 text-ag-subtle">
                                    @if ($licence->activation?->instance_id === $unbound)
                                        <span class="text-ag-ink">Licence file (any console)</span>
                                    @elseif ($licence->activation)
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
                                    @if ($licence->isUsable() && $licence->usedByKey())
                                        <span class="mr-4 inline-flex cursor-not-allowed items-center gap-1.5 text-xs font-medium text-ag-muted opacity-60" title="A console activated this licence with its key, so a licence file cannot be generated" data-testid="licence-file-disabled"><x-glyph name="download" :size="13" /> Licence file</span>
                                    @elseif ($licence->isUsable())
                                        <button type="button" wire:click="startFile({{ $licence->id }})" class="mr-4 inline-flex items-center gap-1.5 text-xs font-medium text-ag-teal-text hover:underline"><x-glyph name="download" :size="13" /> Licence file</button>
                                    @endif
                                    @if ($licence->keyIsVisible())
                                        <button type="button" wire:click="showKey({{ $licence->id }})" title="Viewable until {{ $licence->key_visible_until->format('H:i') }}" class="mr-4 inline-flex items-center gap-1.5 text-xs font-medium text-ag-teal-text hover:underline"><x-glyph name="eye" :size="13" /> Show key</button>
                                    @endif
                                    @if (\App\Support\LicenceManager::lockedUntil($licence))
                                        <span class="inline-flex items-center gap-1.5 text-xs text-ag-muted" title="A licence in use cannot be revoked until it expires" data-testid="licence-in-use"><x-glyph name="lock" :size="13" /> In use{{ $licence->expires_at ? ' until '.$licence->expires_at->format('M j, Y') : '' }}</span>
                                    @else
                                        <button type="button" wire:click="startRevoke({{ $licence->id }})" class="inline-flex items-center gap-1.5 text-xs font-medium text-ag-subtle hover:text-ag-danger-text"><x-glyph name="trash" :size="13" /> Revoke</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    @if ($licenceFiles->isNotEmpty())
    <x-card class="mt-6 p-7">
        <div class="mb-4 flex items-center gap-2 text-xs font-medium text-ag-teal-text"><x-glyph name="download" :size="14" /> Licence files</div>
        <h2 class="mb-1 text-2xl font-medium text-ag-ink">Licence files issued</h2>
        <p class="mb-2 max-w-2xl text-sm text-ag-subtle">Files downloaded for offline consoles, and files consoles received when they activated a licence key online.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ag-line text-left text-[11px] uppercase tracking-[0.14em] text-ag-subtle">
                            <th scope="col" class="py-2 pr-4 font-medium">Licence</th>
                            <th scope="col" class="py-2 pr-4 font-medium">File ID</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Issued</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Expires</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Activation</th>
                            <th scope="col" class="py-2 font-medium">Installation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licenceFiles as $file)
                            <tr class="border-b border-ag-line last:border-0" wire:key="file-{{ $file->id }}">
                                <td class="py-2.5 pr-4 font-mono text-ag-ink">{{ $file->licence?->name ?? '—' }}<span class="block text-xs text-ag-muted">{{ $file->issued_by === 'console' ? 'online activation' : 'download' }}</span></td>
                                <td class="py-2.5 pr-4 font-mono text-xs text-ag-ink">{{ $file->license_id }}</td>
                                <td class="py-2.5 pr-4">{{ $file->issued_at->format('M j, Y H:i') }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-danger-text' => $file->isExpired()])>{{ $file->expires_at->format('M j, Y') }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-subtle' => $file->activationMinutesLeft() === 0, 'text-ag-teal-text' => $file->activationMinutesLeft() > 0])>{{ $file->activationWindowLabel() }}</td>
                                <td class="py-2.5 font-mono text-xs text-ag-subtle">{{ $file->install_id ?? 'Any' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
    </x-card>
    @endif
</div>
