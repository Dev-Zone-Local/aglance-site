<x-layouts.site title="Use cases">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Use cases" title="Built for the teams who get paged." large>
            Six concrete jobs AtGlance was built to do — without forcing you to adopt a SaaS observability stack.
        </x-page-header>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-feature icon="server" title="Multi-host service inventory">Discover every systemd service, port and config across hundreds of Ubuntu hosts in minutes — without installing yet another agent.</x-feature>
            <x-feature icon="git-branch" title="Safe configuration rollouts">Backup before you import. Validate before you apply. Roll back with one flag. Auditable in the Console.</x-feature>
            <x-feature icon="shield-check" title="Compliance-grade auditability">Every register/deregister/config-import action is logged. RBAC enforced server-side. PAT tokens scoped per host.</x-feature>
            <x-feature icon="network" title="Air-gapped operations">Self-host the Console behind your VPN. The CLI talks only to your own gateway. No egress to atglance.live.</x-feature>
            <x-feature icon="activity" title="Resilient under DB outages">Buffer writes in the cache when the database is down. Replay idempotently when it recovers. Operations don't stop.</x-feature>
            <x-feature icon="layers" title="Hybrid + multi-cloud control">Run one Console from a private cloud, manage hosts across on-prem and AWS/Azure/GCP. One source of truth.</x-feature>
        </div>

        <section class="mt-20">
            <x-section-heading eyebrow="Personas" title="Who reaches for AtGlance" />
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['Platform Engineer', 'Can I roll out config changes safely across 300 Ubuntu hosts?'],
                    ['SRE Manager', "Where is the audit trail for last week's incident?"],
                    ['Security / Compliance Lead', 'Does any data leave our perimeter? Who can do what?'],
                ] as [$role, $quote])
                    <x-card class="p-6">
                        <div class="mb-3 text-xs font-medium text-ag-teal-text">{{ $role }}</div>
                        <p class="leading-relaxed text-ag-ink">“{{ $quote }}”</p>
                    </x-card>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.site>
