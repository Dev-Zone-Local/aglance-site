<div>
    <x-page-header eyebrow="Licences" title="Management Console licences">
        Generate a licence file (.atgl) and upload it in the Management Console installer, or in Admin Settings &rsaquo; Licence.
    </x-page-header>

    <x-card class="p-7">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs font-medium text-ag-teal-text"><x-glyph name="key-round" :size="14" /> Licence files</div>
            <div class="font-mono text-xs text-ag-subtle" data-testid="licence-usage">{{ $user->planLabel() }} plan · {{ $activeCount }} / {{ $limit }} active files</div>
        </div>
        <h2 class="mb-1 text-2xl font-medium text-ag-ink">Licence for the Management Console</h2>
        <p class="mb-5 max-w-2xl text-sm text-ag-subtle">
            The console checks the licence file on its own server and never contacts atglance.live, so it also works without
            internet access. Each file counts toward your limit until it expires; it cannot be withdrawn once downloaded.
        </p>

        <div class="mb-6 rounded-input bg-ag-surface p-5" data-testid="licence-policy">
            <div class="mb-3 flex items-center gap-2 text-sm text-ag-ink">
                Policy: <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-white text-ag-teal-text' => $policy['policy_type'] === 'custom', 'bg-white text-ag-subtle' => $policy['policy_type'] !== 'custom'])>{{ $policy['label'] }}</span>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-ag-muted">Licence file limit</dt><dd class="text-ag-ink">{{ $policy['max_files'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Node limit</dt><dd class="text-ag-ink">{{ $policy['max_nodes'] === 0 ? 'Unlimited' : $policy['max_nodes'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">User limit</dt><dd class="text-ag-ink">{{ $policy['max_users'] === 0 ? 'Unlimited' : $policy['max_users'] }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Valid for</dt><dd class="text-ag-ink">{{ $policy['expires_on'] ? 'until '.$policy['expires_on']->format('M j, Y') : $policy['validity_months'].' '.($policy['validity_months'] === 1 ? 'month' : 'months') }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Install within</dt><dd class="text-ag-ink">{{ $policy['activation_window_minutes'] }} minutes</dd></div>
                <div><dt class="text-xs text-ag-muted">Installation ID</dt><dd class="text-ag-ink">{{ $policy['install_id'] ? 'Fixed by AtGlance' : ucfirst($policy['install_id_mode']) }}</dd></div>
                <div><dt class="text-xs text-ag-muted">Email verification</dt><dd class="text-ag-ink">{{ $policy['require_email_code'] ? 'Required' : 'Not required' }}</dd></div>
            </dl>
            <div class="mt-3 text-sm">
                <span class="text-xs text-ag-muted">Features:</span>
                @forelse ($policy['features'] as $feature)
                    <span class="mr-1 inline-block rounded-full bg-white px-2.5 py-0.5 text-xs text-ag-ink">{{ $featureNames[$feature] ?? $feature }}</span>
                @empty
                    <span class="text-ag-subtle">None</span>
                @endforelse
            </div>
        </div>

        @if ($user->license_requires_approval)
            <p class="rounded-input bg-ag-surface px-4 py-3 text-sm text-ag-subtle">Licence file generation is blocked for your account. <a href="{{ route('contact') }}" class="ag-link">Contact AtGlance Support</a>.</p>
        @elseif ($atLimit)
            <div class="rounded-input bg-ag-surface px-4 py-3 text-sm text-ag-subtle" data-testid="licence-limit">
                {{ $limitMessage }}
                <a href="{{ route('contact') }}" class="ag-link">Contact AtGlance Support</a>
            </div>
        @else
            <form method="POST" action="{{ route('dashboard.licences.file') }}" class="max-w-2xl space-y-4">
                @csrf
                @unless ($policy['install_id'])
                    <div>
                        <label for="install-id" class="mb-1.5 block text-sm font-medium text-ag-ink">
                            Installation ID
                            <span class="font-normal text-ag-muted">({{ $policy['install_id_mode'] === 'required' ? 'required' : 'optional' }})</span>
                        </label>
                        <input id="install-id" name="install_id" value="{{ old('install_id') }}" maxlength="64" autocomplete="off" spellcheck="false"
                            @if ($policy['install_id_mode'] === 'required') required @endif
                            placeholder="e.g. 3f2b8c1e-…" class="ag-input bg-white font-mono text-sm" @error('install_id') aria-invalid="true" @enderror>
                        <p class="mt-1.5 text-[13px] text-ag-muted">Shown in the console installer and in Admin Settings &rsaquo; Licence. With it, the file works on that console only.</p>
                        @error('install_id') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                    </div>
                @endunless

                @if ($policy['require_email_code'])
                    <div>
                        <label for="file-code" class="mb-1.5 block text-sm font-medium text-ag-ink">Email verification code</label>
                        {{-- Code input and "Email me a code" on one row; the message goes below. --}}
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input id="file-code" name="code" required inputmode="numeric" pattern="[0-9]{5}" maxlength="5" autocomplete="one-time-code" placeholder="5-digit code"
                                x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 5)"
                                class="ag-input bg-white font-mono text-lg tracking-[0.4em] placeholder:text-sm placeholder:tracking-normal sm:w-44" @error('code') aria-invalid="true" @enderror>
                            <x-button type="button" variant="white" class="shrink-0" wire:click="sendCode" wire:loading.attr="disabled" wire:target="sendCode">Email me a code</x-button>
                        </div>
                        <p class="mt-1.5 text-[13px] text-ag-muted">{{ $codeMessage !== '' ? $codeMessage : 'We email a 5-digit code to '.$user->email.' to confirm it is you.' }}</p>
                        @error('code') <p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
                    </div>
                @endif

                <x-button class="shrink-0"><x-glyph name="download" :size="16" /> Generate licence file</x-button>
                <p class="text-[13px] text-ag-muted">Upload the file in the console within {{ $policy['activation_window_minutes'] }} minutes of generating it.</p>
                @error('license_file') <p class="text-[13px] text-ag-danger-text">{{ $message }}</p> @enderror
            </form>
        @endif

        @if ($licenceFiles->isNotEmpty())
            <div class="mt-8 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ag-line text-left text-[11px] uppercase tracking-[0.14em] text-ag-subtle">
                            <th scope="col" class="py-2 pr-4 font-medium">File ID</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Status</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Issued</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Expires</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Activation</th>
                            <th scope="col" class="py-2 font-medium">Installation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licenceFiles as $file)
                            <tr class="border-b border-ag-line last:border-0" wire:key="file-{{ $file->id }}">
                                <td class="py-2.5 pr-4 font-mono text-xs text-ag-ink">{{ $file->license_id }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-teal-text' => $file->isActive(), 'text-ag-subtle' => ! $file->isActive()])>{{ $file->statusLabel() }}</td>
                                <td class="py-2.5 pr-4">{{ $file->issued_at->format('M j, Y H:i') }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-danger-text' => $file->isExpired()])>{{ $file->expires_at->format('M j, Y') }}</td>
                                <td @class(['py-2.5 pr-4', 'text-ag-subtle' => $file->activationMinutesLeft() === 0, 'text-ag-teal-text' => $file->activationMinutesLeft() > 0])>{{ $file->activationWindowLabel() }}</td>
                                <td class="py-2.5 font-mono text-xs text-ag-subtle">{{ $file->install_id ?? 'Any' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
