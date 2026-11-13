<x-layouts.site title="Product">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Product" title="Two surfaces. One source of truth." large>
            AtGlance gives operations teams a single, auditable surface for service inventory and configuration — without forcing your data outside your boundary.
        </x-page-header>

        <div class="mt-14 grid gap-6 md:grid-cols-2">
            <x-card class="p-7">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint"><x-glyph name="cpu" /></span>
                <h2 class="mb-2 text-2xl font-medium text-ag-ink">CLI agent</h2>
                <p class="mb-5 leading-relaxed text-ag-subtle">One static binary on every Linux host. Reads systemd and the journal, backs up unit files and talks to the console gateway with a personal API key or a service account key.</p>
                <ul class="space-y-2 text-sm text-ag-subtle">
                    @foreach (['status, service <name>, watch, export', 'login, whoami, check', 'system register | deregister | reactivate | rotate-key', 'sync, config backup | list | diff | restore', 'schedule enable | status', 'agent install | start | status (3.0)'] as $flag)
                        <li>· <code class="font-mono text-ag-teal-text">{{ $flag }}</code></li>
                    @endforeach
                </ul>
                <a href="{{ route('cli') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-ag-teal-text transition-all hover:gap-3">CLI reference <x-glyph name="arrow-right" :size="14" /></a>
            </x-card>

            <x-card class="p-7">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint"><x-glyph name="server" /></span>
                <h2 class="mb-2 text-2xl font-medium text-ag-ink">Management Console</h2>
                <p class="mb-5 leading-relaxed text-ag-subtle">Application + REST API + database + cache. Deploy on-prem, in your VPC, or hybrid. RBAC, dashboards, configuration history.</p>
                <ul class="space-y-2 text-sm text-ag-subtle">
                    @foreach (['Web UI for admin/user/superadmin', 'REST APIs behind a declarative, DB-less gateway', 'Queue workers with retry/backoff', 'DatabaseCircuitBreaker middleware', 'Storage: local, S3, NFS or Azure Storage', 'SSO: Google, Microsoft Entra ID, GitHub, GitLab, Okta'] as $item)
                        <li>· {{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('console') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-ag-teal-text transition-all hover:gap-3">Console deep-dive <x-glyph name="arrow-right" :size="14" /></a>
            </x-card>
        </div>

        <section class="mt-20">
            <x-section-heading eyebrow="End-to-end" title="How they fit together" />
            <x-arch-diagram class="mt-8" src="/images/End-to-End Architecture.png" alt="End-to-end architecture" caption="CLI → REST API gateway → Application + Cache + Database" />
        </section>

        <section class="mt-20">
            <x-section-heading eyebrow="System lifecycle" title="Register · Deregister · Reactivate" sub="A safe, idempotent flow for every host." />
            <x-story-flow class="mt-8" :steps="[
                ['title' => 'register', 'desc' => 'sudo atglance system register joins a workspace; the server keeps its own system key.'],
                ['title' => 'sync', 'desc' => 'sudo atglance sync backs up every changed unit file as a new version.'],
                ['title' => 'deregister', 'desc' => 'system deregister sets the system inactive; backups are refused until it is back.'],
                ['title' => 'reactivate', 'desc' => 'system reactivate brings the server back with its id, key and history.'],
            ]" />
        </section>

        <section class="mt-20 grid items-center gap-10 lg:grid-cols-2">
            <x-section-heading eyebrow="Sample" title="A few lines to get going." sub="Settings live in /etc/atglance/config.json and /var/lib/atglance/ (root only)." />
            <x-code-block title="bash" code="sudo apt-get install -y ./atglance_2.2.0_amd64.deb
sudo atglance login --server https://console.example.com
sudo atglance system register --workspace 1 --service-account
sudo atglance check
atglance status" />
        </section>
    </div>
</x-layouts.site>
