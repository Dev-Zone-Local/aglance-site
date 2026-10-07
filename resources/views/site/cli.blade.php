@php
    $flags = [
        ['--version', 'Print CLI version'],
        ['--configure / configure', 'Initial configuration'],
        ['--validate', 'Validate local configuration'],
        ['--system-register [--restore -f/--file]', 'Register the host (optionally restore from backup)'],
        ['--system-deregister', 'Soft decommission'],
        ['--system-deregister-force', 'Hard remove system_id'],
        ['--system-regenerate-hash', 'Rotate the validation hash'],
        ['--system-reactivate', 'Bring host back online'],
        ['--system-reactivate-force', 'Reactivate even with stale hash'],
        ['--show-my-services', 'List discovered systemd services'],
        ['--app <service>', 'Inspect a single service'],
        ['--config-show / --config-import', 'View / import configurations'],
        ['watch [--interval]', 'Continuous health watch'],
        ['export --format json', 'Export inventory'],
    ];
@endphp
<x-layouts.site title="CLI">
    <div class="mx-auto max-w-7xl px-1 py-6 sm:px-4">
        @include('site.partials.showcase', ['product' => 'cli'])

        @if ($page['sections']['terminal'])
        <div class="mt-20 grid items-center gap-10 lg:grid-cols-2">
            <x-terminal title="atglance · live" :lines="[
                '$ atglance --version',
                'atglance v1.0.0 (build d8e9c41)',
                '$ atglance --validate',
                '✓ config.json valid',
                '✓ /etc/environment readable',
                '✓ journalctl + ss available',
                '✓ Console reachable (REST API :8002)',
            ]" />
            <div>
                <h2 class="mb-3 text-2xl font-medium text-ag-ink">Built around <span class="font-mono text-ag-teal-text">main()</span> routing</h2>
                <p class="mb-5 leading-relaxed text-ag-subtle">The CLI's <code class="font-mono text-sm text-ag-teal-text">main()</code> dispatches in a strict order — config, validation, registration, runtime — so behaviour is predictable across distros.</p>
                <a href="{{ route('docs.show', 'cli') }}" class="inline-flex items-center gap-2 text-sm font-medium text-ag-teal-text transition-all hover:gap-3">Full CLI reference <x-glyph name="arrow-right" :size="14" /></a>
            </div>
        </div>
        @endif

        @if ($page['sections']['flags'])
        <section class="mt-20">
            <x-section-heading eyebrow="Flag catalog" title="Every command, one table." />
            <div class="mt-8 overflow-x-auto rounded-card bg-white shadow-ag">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-ag-surface">
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">Flag</th>
                            <th scope="col" class="px-5 py-3 text-left text-xs font-medium uppercase tracking-[0.12em] text-ag-subtle">Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flags as [$flag, $purpose])
                            <tr class="border-t border-ag-line">
                                <td class="whitespace-nowrap px-5 py-3 font-mono text-[13px] text-ag-teal-text">{{ $flag }}</td>
                                <td class="px-5 py-3 text-ag-subtle">{{ $purpose }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        @if ($page['sections']['architecture'])
        <section class="mt-20">
            <x-section-heading eyebrow="Architecture" title="Inside the CLI" />
            <x-arch-diagram class="mt-8" src="/images/AtGlance_CLI Tool Architecture.png" alt="AtGlance CLI architecture" caption="CLI → REST API → Backend · Local runtime & persistent paths" />
        </section>
        @endif

        @if ($page['sections']['lifecycle'])
        <section class="mt-20">
            <x-section-heading eyebrow="Lifecycle" title="register → deregister → reactivate" />
            <x-story-flow class="mt-8" :steps="[
                ['title' => 'register', 'desc' => 'Hash + token issued. system_id persisted to /etc/environment.'],
                ['title' => 'watch', 'desc' => 'Service inventory and ports streamed to Console.'],
                ['title' => 'deregister', 'desc' => 'Soft-remove (audit trail kept) or -force (hard delete).'],
                ['title' => 'reactivate', 'desc' => 'Brings host back without losing system_id or backups.'],
            ]" />
        </section>
        @endif

        @if ($page['sections']['paths'])
        <section class="mt-20">
            <x-section-heading eyebrow="Persistent paths" title="Where state lives" class="mb-8" />
            <x-code-block title="paths" code="~/.config/atglance/config.json   # CLI config (PAT, console URL, org_id)
config-backups/                  # versioned config snapshots
config-imports/                  # staged imports awaiting --validate
/etc/environment                 # validation hash, system_id, org_id" />
        </section>
        @endif
    </div>
</x-layouts.site>
