<x-layouts.site>
    <div class="space-y-16">
        <x-hero class="rise">
            <x-slot:eyebrow><span class="h-1.5 w-1.5 rounded-full bg-ag-mint"></span> Self-hosted · For SREs by SREs</x-slot:eyebrow>
            <x-slot:title>Operations at a glance,<br>inside your boundary.</x-slot:title>
            <p>A CLI-focused operations platform with a calm, auditable CLI and a self-hosted Management Console. No telemetry. No vendor lock-in.</p>
            <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs text-ag-ink-soft">
                @foreach (['Ubuntu / systemd', 'REST API gateway', 'RBAC + PAT tokens', 'DB circuit breaker'] as $h)
                    <li class="flex items-center gap-1.5"><span class="h-1 w-1 rounded-full bg-ag-mint"></span> {{ $h }}</li>
                @endforeach
            </ul>
            <x-slot:actions>
                <x-button :href="route('register')" size="lg">Sign up free <x-glyph name="arrow-right" /></x-button>
                <x-button :href="route('docs.show', 'quickstart')" size="lg" variant="white">Read the quickstart</x-button>
            </x-slot:actions>
            <x-slot:aside>
                {{-- Typing terminal (resources/js/app.js: heroTerminal) --}}
                <div x-data="heroTerminal" class="relative overflow-hidden rounded-card bg-[#0E1114] ring-1 ring-white/10" aria-label="Example CLI session" role="img">
                    <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#E45757]/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-[#D98A0B]/80"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-[#1FA874]/80"></span>
                        <span class="ml-3 font-mono text-[11px] text-[#8A9099]">sre@web-01 — atglance</span>
                        <span class="ml-auto font-mono text-[10px] text-[#71F7D4]">● live</span>
                    </div>
                    <div class="min-h-[320px] overflow-x-auto p-5 font-mono text-[12px] leading-7 sm:text-[13px] [&_div]:whitespace-nowrap" aria-hidden="true">
                        <template x-for="(h, i) in history" :key="'h' + i">
                            <div class="mb-3 opacity-60">
                                <div><span class="text-[#71F7D4]">$</span> <span class="text-white" x-text="h.t"></span></div>
                                <template x-for="(o, j) in h.out" :key="j"><div class="text-[#8A9099]" x-text="o"></div></template>
                            </div>
                        </template>
                        <div><span class="text-[#71F7D4]">$</span> <span class="text-white" x-text="typed"></span><span x-show="typing" class="cursor"></span></div>
                        <template x-for="(o, i) in output" :key="'o' + i"><div class="rise text-[#B7BEC6]" x-text="o"></div></template>
                    </div>
                </div>
            </x-slot:aside>
        </x-hero>

        {{-- Two surfaces --}}
        <section>
            <x-section-heading eyebrow="One platform · Two surfaces" title="A CLI for the host. A Console for the org."
                sub="The CLI runs on every Linux/systemd host and handles discovery, registration and configuration safety. The Console runs inside your boundary and gives operations teams dashboards, RBAC and resilience." />
            <div class="mt-8 grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['cli', 'cpu', 'AtGlance CLI', '/usr/local/bin/atglance', 'Explore the CLI', 'A focused agent for systemd hosts: service discovery, health, configuration backup and import, registration, deregistration and reactivation.'],
                    ['console', 'server', 'Management Console', 'Self-hosted', 'Explore the Console', 'Self-hosted console with REST API gateway, database and cache. Dashboards, RBAC, configuration history, queue resilience and a database circuit breaker.'],
                ] as [$route, $icon, $title, $meta, $cta, $text])
                    <a href="{{ route($route) }}" class="group block rounded-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                        <x-card hover class="h-full p-7">
                            <div class="mb-5 flex items-center justify-between">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint"><x-glyph :name="$icon" /></span>
                                <span class="font-mono text-[11px] text-ag-muted">{{ $meta }}</span>
                            </div>
                            <h3 class="mb-2 text-[22px] font-medium tracking-heading text-ag-ink">{{ $title }}</h3>
                            <p class="mb-5 leading-relaxed text-ag-subtle">{{ $text }}</p>
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text transition-all group-hover:gap-2.5">{{ $cta }} <x-glyph name="arrow-right" :size="14" /></span>
                        </x-card>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Features --}}
        <section>
            <x-section-heading eyebrow="Why AtGlance" title="Calm operations. Strict boundaries." />
            <div class="mt-6 grid grid-cols-3 gap-4 max-[900px]:grid-cols-2 max-[560px]:grid-cols-1">
                <x-feature icon="shield-check" title="Self-hosted by design">Deploy the entire Console inside your VPC, on-prem or hybrid. No data leaves your perimeter.</x-feature>
                <x-feature icon="activity" title="Native systemd discovery">The CLI reads journalctl and ss/netstat on Ubuntu hosts to discover services and their ports. No extra daemons.</x-feature>
                <x-feature icon="git-branch" title="Configuration safety">Every config change writes a versioned backup. Import and validate before applying. Rollback is a single flag.</x-feature>
                <x-feature icon="lock" title="RBAC + PAT tokens">Three roles (superadmin, admin, user) and short-lived Personal Access Tokens for the CLI. Every action is auditable.</x-feature>
                <x-feature icon="database" title="DB circuit breaker">When the database is unhealthy, writes are buffered as queued jobs and replayed idempotently when it recovers.</x-feature>
                <x-feature icon="network" title="One REST API">A single REST surface for registration, configuration files and token validation, fronted by a rate-limited gateway.</x-feature>
            </div>
        </section>

        {{-- Resilience story --}}
        <section>
            <x-section-heading eyebrow="Resilience story" title="Database outage? Your writes don't disappear." sub="What happens behind the curtain when the database goes down." />
            <x-story-flow class="mt-8" :steps="[
                ['title' => 'DB outage detected', 'desc' => 'The circuit breaker trips. Reads fall back to cache where safe.'],
                ['title' => 'Writes are queued', 'desc' => 'Mutations are encoded as jobs and buffered in the cache. Clients get fast acks.'],
                ['title' => 'DB recovers', 'desc' => 'Health checks pass; the circuit closes. Workers pick up where they left off.'],
                ['title' => 'Jobs replay', 'desc' => 'Queue workers apply buffered writes idempotently. State converges. Audit logs restored.'],
            ]" />
        </section>

        {{-- Architecture peek --}}
        <section>
            <x-section-heading eyebrow="Architecture" title="A boring, predictable stack."
                sub="A REST gateway fronts the Console, backed by a database and a cache. The CLI talks to the gateway over HTTPS. That's it." />
            <x-arch-diagram class="mt-8" src="/images/End-to-End Architecture.png" alt="AtGlance end-to-end architecture" caption="End-to-end · CLI → Gateway → Console → Database / Cache" />
            <div class="mt-6 text-center">
                <a href="{{ route('architecture') }}" class="inline-flex items-center gap-2 text-sm font-medium text-ag-teal-text transition-all hover:gap-3">Read the architecture deep-dive <x-glyph name="arrow-right" :size="14" /></a>
            </div>
        </section>

        {{-- Install banner --}}
        <section class="ag-hero px-6 py-8 sm:px-10 sm:py-12">
            <div class="grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <h2 class="mb-3 text-[26px] font-medium tracking-heading text-white sm:text-[30px]">One curl. systemd-friendly. Idempotent.</h2>
                    <p class="max-w-lg text-base leading-[1.7] text-ag-ink-soft">The installer sets up the CLI package. The CLI writes its config to ~/.config/atglance and registers your host with the Console.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <x-button :href="route('register')">Get started free <x-glyph name="arrow-right" /></x-button>
                        <x-button :href="route('docs.show', 'cli')" variant="white">CLI reference</x-button>
                    </div>
                </div>
                <x-terminal title="install" class="ring-1 ring-white/10" :lines="[
                    '$ curl -fsSL https://app.atglance.live/cli/atglance-installer.sh | sudo bash',
                    '→ Detected: Ubuntu 22.04 · systemd 249',
                    '→ Installed atglance → /usr/local/bin/atglance',
                    '$ atglance --configure',
                    '✓ Wrote ~/.config/atglance/config.json',
                    '$ atglance --system-register',
                    '✓ Registered system_id=sys_8e1a in org=acme',
                ]" />
            </div>
        </section>
    </div>
</x-layouts.site>
