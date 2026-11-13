{{--
    /cli. Hero and features: Admin → Product pages (defaults in ProductShowcase). Install tabs:
    the latest files from Admin → Downloads. Command reference: content/cli-commands.json.
--}}
@php
    $shot = fn (string $n) => asset("screenshots/cli/cli-{$n}.svg");
    // Real terminal captures of one workflow, in order.
    $workflow = [
        ['login', 'Log in', 'sudo atglance login', 'Save the console URL and your personal API key. The key is read from a hidden prompt.'],
        ['register', 'Register', 'sudo atglance system register', 'The server joins a workspace and keeps its own system key, readable only by root.'],
        ['check', 'Check', 'sudo atglance check', 'Every part of the setup in one list: ok, warn or fail.'],
        ['sync', 'Back up', 'sudo atglance sync', 'Every changed unit file is uploaded as a new version. Unchanged files are skipped.'],
        ['config-diff', 'Compare', 'sudo atglance config diff <id>', 'See how a stored version differs from the live file before you restore it.'],
        ['schedule-enable', 'Automate', 'sudo atglance schedule enable', 'A daily check and sync from cron, with the service account key.'],
    ];
    $groups = $commands['groups'] ?? [];
@endphp
<x-layouts.site title="CLI" description="The AtGlance CLI: server health for every systemd service, versioned config backups and safe restore. One static binary for Linux, plus the agent for Windows and macOS.">
    <div class="mx-auto max-w-7xl px-1 py-6 sm:px-4">
        @include('site.partials.showcase', ['product' => 'cli'])

        @if ($page['sections']['install'] ?? true)
        <section id="install" class="mt-20 scroll-mt-24">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <x-section-heading eyebrow="Install" title="Install in one minute"
                    sub="Pick your system. The package installs /usr/bin/atglance, the man page and bash completion. It adds no service and no cron job." />
                <a href="{{ route('downloads') }}#cli" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">All files and checksums <x-glyph name="arrow-right" :size="14" /></a>
            </div>
            <x-cli-install :files="$files" id="install-tabs" class="mt-6" />
        </section>
        @endif

        @if ($page['sections']['terminal'])
        <section id="workflow" class="mt-20 scroll-mt-24"
            x-data="{
                step: 0, count: {{ count($workflow) }}, playing: false, timer: null,
                reduce: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                go(i) { this.step = (i + this.count) % this.count },
                toggle() {
                    this.playing = !this.playing;
                    clearInterval(this.timer);
                    if (this.playing) this.timer = setInterval(() => this.go(this.step + 1), 4000);
                },
            }" x-on:keydown.left="go(step - 1)" x-on:keydown.right="go(step + 1)">
            <x-section-heading eyebrow="A typical workflow" title="From a new server to versioned backups"
                sub="Real output from the CLI, step by step. Use the arrow keys or the steps to move." />
            <div class="mt-8 grid gap-6 lg:grid-cols-[320px_1fr]">
                <ol class="space-y-2" aria-label="Workflow steps">
                    @foreach ($workflow as $i => [$img, $title, $cmd, $text])
                        <li>
                            <button type="button" x-on:click="go({{ $i }})" :aria-current="step === {{ $i }} ? 'step' : null"
                                :class="step === {{ $i }} ? 'bg-white shadow-ag-strong' : 'bg-white/50 hover:bg-white'"
                                class="flex w-full items-start gap-3 rounded-2xl px-4 py-3 text-left transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                                <span :class="step === {{ $i }} ? 'bg-ag-ink text-white' : 'bg-ag-surface text-ag-subtle'" class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-xs">{{ $i + 1 }}</span>
                                <span>
                                    <span class="block text-sm font-medium text-ag-ink">{{ $title }}</span>
                                    <span class="block font-mono text-xs text-ag-teal-text">{{ $cmd }}</span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                    <li class="pt-2">
                        <button type="button" x-show="!reduce" x-on:click="toggle()" class="inline-flex items-center gap-2 rounded-full bg-ag-surface px-4 py-2 text-sm font-medium text-ag-ink hover:bg-ag-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal"
                            :aria-pressed="playing.toString()">
                            <span x-text="playing ? 'Pause' : 'Play all steps'">Play all steps</span>
                        </button>
                    </li>
                </ol>
                <div class="min-w-0">
                    @foreach ($workflow as $i => [$img, $title, $cmd, $text])
                        <figure x-show="step === {{ $i }}" @if ($i > 0) x-cloak @endif class="min-w-0">
                            <img src="{{ $shot($img) }}" alt="Terminal output of {{ $cmd }}" loading="lazy" class="h-auto w-full rounded-[14px] shadow-ag-strong">
                            <figcaption class="mt-3 text-sm text-ag-subtle">{{ $text }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        @if ($page['sections']['flags'] && $groups)
        <section id="commands" class="mt-20 scroll-mt-24"
            x-data="{ q: '', all: @js(collect($groups)->flatMap(fn ($g) => collect($g['commands'])->map(fn ($c) => $c['cmd'].' '.$c['args'].' '.$c['text'].' '.$g['title']))->values()),
                match(text) { return this.q.trim() === '' || text.toLowerCase().includes(this.q.trim().toLowerCase()) },
                get none() { return ! this.all.some((t) => this.match(t)) } }">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <x-section-heading eyebrow="Command reference" title="Every command, one search away"
                    :sub="'Version '.($commands['version'] ?? '').'. Commands marked sudo need root. The full guide is in man atglance.'" />
                <label class="mb-3 w-full sm:w-80">
                    <span class="sr-only">Search commands</span>
                    <input type="search" x-model="q" placeholder="Search: backup, restore, schedule..." autocomplete="off"
                        class="w-full rounded-full border-0 bg-white px-4 py-2.5 text-sm text-ag-ink shadow-ag placeholder:text-ag-muted focus:ring-2 focus:ring-ag-teal">
                </label>
            </div>
            <div class="mt-6 space-y-6">
                @foreach ($groups as $g)
                    @php($groupText = collect($g['commands'])->map(fn ($c) => $c['cmd'].' '.$c['args'].' '.$c['text'])->implode(' ').' '.$g['title'])
                    <div x-show="match(@js($groupText))" class="overflow-hidden rounded-card bg-white shadow-ag">
                        <div class="flex flex-wrap items-baseline gap-2 bg-ag-surface px-5 py-3">
                            <h3 class="text-sm font-medium text-ag-ink">{{ $g['title'] }}</h3>
                            @if (! empty($g['note']))<span class="text-xs text-ag-subtle">{{ $g['note'] }}</span>@endif
                        </div>
                        <dl>
                            @foreach ($g['commands'] as $c)
                                <div x-show="match(@js($c['cmd'].' '.$c['args'].' '.$c['text'].' '.$g['title']))" class="grid gap-1 border-t border-ag-line px-5 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] md:gap-6">
                                    <dt class="min-w-0 font-mono text-[13px]">
                                        @if ($c['sudo'])<span class="text-ag-muted">sudo </span>@endif<span class="text-ag-teal-text">{{ $c['cmd'] }}</span>
                                        @if ($c['args'])<span class="break-words text-ag-subtle"> {{ $c['args'] }}</span>@endif
                                    </dt>
                                    <dd class="text-sm leading-relaxed text-ag-subtle">{{ $c['text'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
                <p x-show="none" x-cloak class="text-sm text-ag-subtle" role="status">No command matches. Try another word.</p>
            </div>
        </section>
        @endif

        @if ($page['sections']['together'] ?? true)
        <section class="mt-20">
            <x-section-heading eyebrow="CLI and Console" title="See health on the server. Keep history in the Console."
                sub="The health commands need nothing else. Connect a Management Console to keep every version, review it with AI and manage teams." />
            <ol class="mt-8 grid gap-4 md:grid-cols-4">
                @foreach ([
                    ['server', 'Install the Console', 'Once, on any Docker host. Create an API key under Settings.', route('downloads').'#console'],
                    ['download', 'Install the CLI', 'On each Linux server, from a .deb, .rpm or .tar.gz.', '#install'],
                    ['git-branch', 'Register and sync', 'login, system register, then sync backs up every unit file.', '#workflow'],
                    ['calendar', 'Automate', 'schedule enable runs a daily check and sync from cron.', '#commands'],
                ] as $i => [$icon, $t, $d, $href])
                    <li class="rounded-card bg-white p-5 shadow-ag">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-ag-ink text-white"><x-glyph :name="$icon" :size="16" /></span>
                        <div class="mt-3 font-mono text-xs text-ag-subtle">Step {{ $i + 1 }}</div>
                        <a href="{{ $href }}" class="mt-1 block font-medium text-ag-ink hover:text-ag-teal-text">{{ $t }}</a>
                        <p class="mt-1 text-sm text-ag-subtle">{{ $d }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
        @endif

        @if ($page['sections']['architecture'])
        <section class="mt-20">
            <x-section-heading eyebrow="Architecture" title="Inside the CLI" />
            <x-arch-diagram class="mt-8" src="/images/AtGlance_CLI Tool Architecture.png" alt="AtGlance CLI architecture" caption="CLI → API gateway (port 8002) → Management Console" />
        </section>
        @endif

        @if ($page['sections']['paths'])
        <section class="mt-20 grid gap-6 lg:grid-cols-2">
            <div>
                <x-section-heading eyebrow="Files" title="Where state lives" class="mb-6" />
                <x-code-block title="files (root only, mode 0600)" code="/etc/atglance/config.json               # console URL, personal API key
/var/lib/atglance/identity.json          # system id and key
/var/lib/atglance/service-account.json   # key for scheduled runs
/var/lib/atglance/queue/                 # backups waiting for the console
/var/lib/atglance/backups/<service>/     # local copies
/etc/cron.d/atglance                     # daily check and sync" />
            </div>
            <div>
                <x-section-heading eyebrow="Exit codes" title="Made for scripts" class="mb-6" />
                <div class="overflow-hidden rounded-card bg-white shadow-ag">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Exit codes</caption>
                        <tbody>
                            @foreach ([['0', 'Success'], ['1', 'Error: systemd or console not reachable, refused, not found'], ['2', 'Usage error'], ['3', 'Degraded: a service is Failed or Restarting, or config diff found a difference']] as [$code, $meaning])
                                <tr class="border-t border-ag-line first:border-t-0">
                                    <th scope="row" class="w-16 px-5 py-3 text-left font-mono text-ag-teal-text">{{ $code }}</th>
                                    <td class="px-5 py-3 text-ag-subtle">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        @endif
    </div>
</x-layouts.site>
