<x-layouts.site title="Management Console">
    <div class="mx-auto max-w-7xl px-1 py-6 sm:px-4">
        @include('site.partials.showcase', ['product' => 'console'])

        @if ($page['sections']['architecture'])
        <section class="mt-20">
            <x-section-heading eyebrow="Architecture" title="The Console under the hood" />
            <x-arch-diagram class="mt-8" src="/images/Atglance System Architecture.png" alt="AtGlance system architecture" caption="Application · REST API · Database · Cache · File storage" />
        </section>

        @endif

        @if ($page['sections']['resilience'])
        <section class="mt-20">
            <x-section-heading eyebrow="Resilience flow" title="What happens during a DB outage" />
            <x-story-flow class="mt-8" :steps="[
                ['title' => 'Detect', 'desc' => 'DatabaseCircuitBreaker probes the database. Threshold tripped → circuit open.'],
                ['title' => 'Buffer', 'desc' => 'Writes are encoded as jobs and pushed to the cache queue \'buffer\'.'],
                ['title' => 'Recover', 'desc' => 'Probes succeed again. Circuit half-open → full close.'],
                ['title' => 'Replay', 'desc' => 'Workers drain the buffer queue. Jobs are idempotent by design.'],
            ]" />
        </section>

        @endif

        @if ($page['sections']['rbac'])
        <section class="mt-20">
            <x-section-heading eyebrow="RBAC matrix" title="Three roles. One source of truth." />
            <x-rbac-table class="mt-8" />
        </section>

        @endif

        @if ($page['sections']['deploy'])
        <section class="mt-20">
            <x-section-heading eyebrow="Deploy" title="Running in five minutes"
                sub="The installer sets up Docker if needed, pulls the Console images and starts them. It asks for a licence key from your AtGlance dashboard." />
            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                <x-code-block title="Linux" :code="'curl -fsSL '.($platforms['linux']['url'] ?? 'https://app.atglance.live/console/atglance-installer.sh').' | sudo bash'" />
                <x-code-block title="Windows (PowerShell as Administrator)" :code="'irm '.($platforms['windows']['url'] ?? 'https://app.atglance.live/console/atglance-installer.ps1').' | iex'" />
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <x-button :href="route('dashboard.install', ['console'])">Guided install <x-glyph name="arrow-right" /></x-button>
                <x-button :href="route('downloads').'#console'" variant="white">All console downloads <x-glyph name="arrow-right" :size="14" /></x-button>
                <x-button :href="route('pricing')" variant="white">Pricing</x-button>
            </div>
        </section>
        @endif

        @if ($page['sections']['compare'] ?? true)
        <section class="mt-20">
            <x-section-heading eyebrow="CLI or Console" title="Which one do I use?"
                sub="Both. The CLI runs on each server; the Console keeps the history and the controls for your team." />
            <div class="mt-8 overflow-x-auto rounded-card bg-white shadow-ag">
                <table class="w-full min-w-[560px] text-sm">
                    <caption class="sr-only">CLI compared with the Management Console</caption>
                    <thead>
                        <tr class="bg-ag-surface text-left text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">
                            <th scope="col" class="px-5 py-3">Task</th>
                            <th scope="col" class="px-5 py-3">CLI</th>
                            <th scope="col" class="px-5 py-3">Console</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['See service health on one server', 'atglance status, service, watch', 'Systems and live monitoring for the whole fleet'],
                            ['Back up unit files', 'sync, config backup', 'Keeps every version, per server and service'],
                            ['Compare and restore', 'config diff, config restore with rollback', 'View, compare by hash and download any version'],
                            ['Find risky settings', '—', 'AI configuration review and Vulnerabilities page'],
                            ['Automate', 'schedule enable (daily check and sync)', 'Service accounts, notifications, scheduled backups'],
                            ['Manage people', '—', 'Workspaces, roles, SSO, activity history'],
                            ['Works without the other', 'Yes, for health commands', 'Yes, but servers report through the CLI or agent'],
                        ] as [$task, $cli, $console])
                            <tr class="border-t border-ag-line">
                                <th scope="row" class="px-5 py-3 text-left font-medium text-ag-ink">{{ $task }}</th>
                                <td class="px-5 py-3 font-mono text-[13px] text-ag-teal-text">{{ $cli }}</td>
                                <td class="px-5 py-3 text-ag-subtle">{{ $console }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <x-button :href="route('cli')" variant="white">Explore the CLI <x-glyph name="arrow-right" :size="14" /></x-button>
                <x-button :href="route('downloads')">Downloads <x-glyph name="arrow-right" :size="14" /></x-button>
            </div>
        </section>
        @endif
    </div>
</x-layouts.site>
