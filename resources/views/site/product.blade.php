<x-layouts.site title="Product">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Product" title="Two surfaces. One source of truth." large>
            AtGlance gives operations teams a single, auditable surface for service inventory and configuration — without forcing your data outside your boundary.
        </x-page-header>

        <div class="mt-14 grid gap-6 md:grid-cols-2">
            <x-card class="p-7">
                <span class="mb-5 inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint"><x-glyph name="cpu" /></span>
                <h2 class="mb-2 text-2xl font-medium text-ag-ink">CLI agent</h2>
                <p class="mb-5 leading-relaxed text-ag-subtle">Runs on every host. Reads systemd, journalctl, ss/netstat. Writes config backups. Talks to the REST API over HTTPS using a PAT token.</p>
                <ul class="space-y-2 text-sm text-ag-subtle">
                    @foreach (['--system-register / --restore -f/--file', '--system-deregister[-force]', '--system-reactivate[-force]', '--show-my-services, --app <svc>', '--config-show, --config-import', 'watch [--interval], export --format json'] as $flag)
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
                    @foreach (['Web UI for admin/user/superadmin', 'REST APIs behind a declarative, DB-less gateway', 'Queue workers with retry/backoff', 'DatabaseCircuitBreaker middleware', 'File storage: local or S3', 'SSO providers: GitHub, Azure AD, Okta, Auth0, Google'] as $item)
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
                ['title' => 'register', 'desc' => 'atglance --system-register hashes /etc/environment and registers org+system.'],
                ['title' => 'watch & sync', 'desc' => 'atglance watch streams services and ports. Configs are saved as backups.'],
                ['title' => 'deregister', 'desc' => '--system-deregister soft-decommissions; -force removes the system_id.'],
                ['title' => 'reactivate', 'desc' => '--system-reactivate brings the host back without losing identity.'],
            ]" />
        </section>

        <section class="mt-20 grid items-center gap-10 lg:grid-cols-2">
            <x-section-heading eyebrow="Sample" title="A few lines to get going." sub="Configuration lives in ~/.config/atglance/config.json." />
            <x-code-block title="bash" code="curl -fsSL https://app.atglance.live/cli/atglance-installer.sh | sudo bash
sudo atglance --configure
sudo atglance --system-register
sudo atglance --validate
sudo atglance --show-my-services" />
        </section>
    </div>
</x-layouts.site>
