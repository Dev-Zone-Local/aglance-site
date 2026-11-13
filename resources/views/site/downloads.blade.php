{{--
    /downloads: the latest CLI and Management Console in one place (replaces app.atglance.live).
    Files come from Admin → Downloads; upload a new release there and this page, /cli, /console,
    the release notes and the dashboard installer all show it.
--}}
@php
    use App\Support\Downloads;
    use Illuminate\Support\Carbon;

    $cli = $products['cli'];
    $console = $products['console'];
    $cLinux = $console['data']['platforms']['linux'] ?? [];
    $cWin = $console['data']['platforms']['windows'] ?? [];
    $date = fn ($r) => ! empty($r['released_at']) ? Carbon::parse($r['released_at'])->format('d M Y') : null;
@endphp
<x-layouts.site title="Downloads" description="Download the latest AtGlance CLI for Linux and Windows, and install or update the self-hosted Management Console.">
    <div class="mx-auto max-w-6xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Downloads" title="Get AtGlance running" large>
            Install the Management Console once, then the CLI on each server. Every file has a published SHA-256 checksum.
        </x-page-header>

        <nav class="mt-6 grid gap-4 sm:grid-cols-2" aria-label="Products">
            @foreach (['console' => ['server', 'Management Console', 'Self-hosted console for your organization. Linux or Windows, with Docker.'], 'cli' => ['terminal', 'AtGlance CLI', 'For each Linux server, plus the agent for Windows Server and macOS.']] as $key => [$icon, $name, $text])
                <a href="#{{ $key }}" class="lift flex items-start gap-4 rounded-card bg-white p-5 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ag-ink text-white"><x-glyph :name="$icon" :size="18" /></span>
                    <span>
                        <span class="flex flex-wrap items-center gap-2 font-medium text-ag-ink">{{ $name }}
                            @if ($products[$key]['latest'])<x-badge variant="success">v{{ $products[$key]['latest']['version'] }}</x-badge>@endif
                        </span>
                        <span class="mt-1 block text-sm text-ag-subtle">{{ $text }}</span>
                    </span>
                </a>
            @endforeach
        </nav>

        {{-- 1. Management Console --}}
        <section id="console" class="mt-16 scroll-mt-24" aria-labelledby="console-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <div class="mb-2 text-xs font-medium text-ag-teal-text">Step 1 · once per organization</div>
                    <h2 id="console-title" class="text-[28px] font-medium tracking-heading text-ag-ink">Management Console</h2>
                </div>
                @if ($console['latest'])
                    <a href="{{ Downloads::releaseUrl('console', $console['latest']['version']) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
                        v{{ $console['latest']['version'] }} release notes @if ($date($console['latest']))· {{ $date($console['latest']) }}@endif <x-glyph name="arrow-right" :size="14" />
                    </a>
                @endif
            </div>
            <p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-ag-subtle">
                The installer checks the server, installs Docker if needed, pulls the console images and starts them. It asks for a licence key from your
                <a href="{{ route('dashboard.licences') }}" class="text-ag-teal-text underline">AtGlance dashboard</a>.
                The updater backs up first, updates every container and checks health after.
            </p>

            <div class="mt-6" x-data="{ os: 'linux' }">
                <div class="inline-flex rounded-full bg-white p-1 shadow-ag" role="tablist" aria-label="Console platform">
                    @foreach (['linux' => 'Linux', 'windows' => 'Windows'] as $os => $label)
                        <button type="button" role="tab" id="console-tab-{{ $os }}" aria-controls="console-panel-{{ $os }}" x-on:click="os = '{{ $os }}'" :aria-selected="os === '{{ $os }}'"
                            :class="os === '{{ $os }}' ? 'bg-ag-ink text-white' : 'text-ag-subtle hover:text-ag-ink'"
                            class="rounded-full px-4 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">{{ $label }}</button>
                    @endforeach
                </div>

                @foreach (['linux' => [$cLinux, 'bash', 'curl -fsSL %s | sudo bash', 'Run as root on a Linux server (amd64).'], 'windows' => [$cWin, 'PowerShell as Administrator', 'irm %s | iex', 'Windows 10/11 or Windows Server with Docker Desktop in Linux containers mode.']] as $os => [$p, $shell, $tpl, $note])
                    <div id="console-panel-{{ $os }}" role="tabpanel" aria-labelledby="console-tab-{{ $os }}" x-show="os === '{{ $os }}'" @if ($os !== 'linux') x-cloak @endif class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-card bg-white p-5 shadow-ag">
                            <div class="font-medium text-ag-ink">Install</div>
                            <p class="mt-1 text-sm text-ag-subtle">{{ $note }}</p>
                            @if (! empty($p['url']))
                                <x-code-block :title="$shell" :code="sprintf($tpl, $p['url'])" class="mt-4" />
                                <a href="{{ $p['url'] }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-ag-teal-text hover:underline"><x-glyph name="download" :size="14" /> {{ $p['file_name'] ?? 'Download installer' }}</a>
                            @else
                                <p class="mt-4 text-sm text-ag-subtle">The installer is not published yet.</p>
                            @endif
                        </div>
                        <div class="rounded-card bg-white p-5 shadow-ag">
                            <div class="font-medium text-ag-ink">Update an existing console</div>
                            <p class="mt-1 text-sm text-ag-subtle">Keeps your data, passwords and settings. Exit code 2 means a check failed; the backup folder has a ROLLBACK.txt.</p>
                            @if (! empty($p['script_url']))
                                <x-code-block :title="$shell" :code="sprintf($tpl, $p['script_url'])" class="mt-4" />
                            @else
                                <p class="mt-4 text-sm text-ag-subtle">The update script is not published yet.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ([['cpu', 'Server', '2 vCPU, 2 GB RAM, 5 GB disk. Root, sudo or Administrator.'], ['network', 'Ports', 'Open 8000 (web console) and 8002 (gateway for the CLI). Keep 3306 and 6379 closed.'], ['globe', 'After install', 'Open http://<server-ip>:8000 and finish the setup wizard. Put HTTPS in front before production.']] as [$icon, $t, $d])
                    <div class="rounded-card bg-white p-5 shadow-ag">
                        <div class="flex items-center gap-2 font-medium text-ag-ink"><x-glyph :name="$icon" :size="16" class="text-ag-teal" /> {{ $t }}</div>
                        <p class="mt-1 text-sm text-ag-subtle">{{ $d }}</p>
                    </div>
                @endforeach
            </div>

            <x-release-files :files="$console['files']" caption="Management Console files" class="mt-4" />
        </section>

        {{-- 2. CLI --}}
        <section id="cli" class="mt-20 scroll-mt-24" aria-labelledby="cli-title">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <div class="mb-2 text-xs font-medium text-ag-teal-text">Step 2 · on each server</div>
                    <h2 id="cli-title" class="text-[28px] font-medium tracking-heading text-ag-ink">AtGlance CLI @if ($cli['latest'])<span class="text-ag-subtle">v{{ $cli['latest']['version'] }}</span>@endif</h2>
                </div>
                @if ($cli['latest'])
                    <a href="{{ Downloads::releaseUrl('cli', $cli['latest']['version']) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
                        Release notes @if ($date($cli['latest']))· {{ $date($cli['latest']) }}@endif <x-glyph name="arrow-right" :size="14" />
                    </a>
                @endif
            </div>
            <p class="mt-2 max-w-3xl text-[15px] leading-relaxed text-ag-subtle">
                One static binary for Linux with systemd 230 or newer. No daemon, no dependencies. The package installs <span class="font-mono text-sm">/usr/bin/atglance</span>, the man page and bash completion.
            </p>

            <x-cli-install :files="$cli['files']" class="mt-6" />

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="rounded-card bg-white p-5 shadow-ag">
                    <div class="font-medium text-ag-ink">Connect the server to your console</div>
                    <p class="mt-1 text-sm text-ag-subtle">Create a personal API key (atgla-) in the console under Settings → API keys. The key is read from a hidden prompt.</p>
                    <x-code-block title="bash" class="mt-4" code="sudo atglance login --server https://console.example.com
sudo atglance system register --workspace 1 --service-account
sudo atglance check" />
                </div>
                <div class="rounded-card bg-white p-5 shadow-ag">
                    <div class="font-medium text-ag-ink">Verify the download</div>
                    <p class="mt-1 text-sm text-ag-subtle">Download SHA256SUMS next to the package; the command prints OK for each genuine file.</p>
                    @if ($cli['latest'] && $cli['files']->isNotEmpty())
                        @php($sums = route('downloads.file', ['cli', $cli['files']->first()->folder, 'SHA256SUMS']))
                        <x-code-block title="bash" class="mt-4" :code="'curl -fsSLO '.$sums.'
sha256sum -c SHA256SUMS --ignore-missing'" />
                    @endif
                </div>
            </div>

            <x-release-files :files="$cli['files']" caption="AtGlance CLI files" class="mt-4" />

            @if ($cli['older'])
                <div class="mt-6 rounded-card bg-white p-5 shadow-ag">
                    <div class="font-medium text-ag-ink">Older versions</div>
                    <p class="mt-1 text-sm text-ag-subtle">Each release page lists its files. Moving back keeps the login, system id and key, queue and schedule.</p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($cli['older'] as $r)
                            <li><a href="{{ Downloads::releaseUrl('cli', $r['version']) }}" class="inline-flex rounded-full bg-ag-surface px-3 py-1.5 font-mono text-[13px] text-ag-ink hover:bg-ag-line">v{{ $r['version'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <div class="mt-14 flex flex-wrap gap-3">
            <x-button :href="route('dashboard.install')">Guided install <x-glyph name="arrow-right" /></x-button>
            <x-button :href="route('releases')" variant="white">All release notes</x-button>
            <x-button :href="route('docs')" variant="white">Docs</x-button>
        </div>
    </div>
</x-layouts.site>
