<x-layouts.site title="Architecture">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Architecture" title="Everything you need. Nothing you don't." large>
            Three views — the CLI, the Console internals, and the end-to-end picture — at increasing levels of detail. All inside your boundary.
        </x-page-header>

        <section class="mt-16">
            <x-section-heading eyebrow="View 1 · CLI" title="AtGlance CLI" sub="Ubuntu/systemd host. Reads runtime, writes backups, talks to the REST API." />
            <x-arch-diagram class="mt-8" src="/images/AtGlance_CLI Tool Architecture.png" alt="CLI architecture" caption="CLI · Local runtime · Backend gateway" />
        </section>

        <section class="mt-20">
            <x-section-heading eyebrow="View 2 · Console" title="AtGlance Management System" sub="Application, REST API gateway, database, cache (queue + cache + buffer), file storage, SSO providers." />
            <x-arch-diagram class="mt-8" src="/images/Atglance System Architecture.png" alt="System architecture" caption="Console · Application · Cache · Database · Storage · SSO" />
        </section>

        <section class="mt-20">
            <x-section-heading eyebrow="View 3 · End-to-end" title="Full picture" sub="The CLI commands, the gateway, the application layer, the data plane, and future capabilities." />
            <x-arch-diagram class="mt-8" src="/images/End-to-End Architecture.png" alt="End-to-end architecture" caption="Operators → CLI → REST API → Application → Cache/Database → Future" />
        </section>

        <section class="mt-20 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Operators', 'SREs, admins, developers — all interact through the CLI or the Console UI.'],
                ['AtGlance CLI', 'main() routes through configure/validate/register/runtime/export.'],
                ['REST API gateway', 'DB-less, declarative config. HTTPS on :8002. Rate-limited.'],
                ['Application', 'Web UI, REST APIs, RBAC middleware, DatabaseCircuitBreaker, job dispatch.'],
                ['Cache', 'Queue (jobs), cache (reads), buffer (writes during a database outage).'],
                ['Database', 'Users, orgs, workspaces, systems, services, configuration, files, raw data, tokens, activity logs.'],
            ] as [$title, $desc])
                <x-card>
                    <h3 class="mb-2 text-xs font-medium text-ag-teal-text">{{ $title }}</h3>
                    <p class="text-sm leading-relaxed text-ag-subtle">{{ $desc }}</p>
                </x-card>
            @endforeach
        </section>
    </div>
</x-layouts.site>
