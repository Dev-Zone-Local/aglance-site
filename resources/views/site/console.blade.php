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
                <x-code-block title="Linux" code="curl -fsSL https://app.atglance.live/console/atglance-installer.sh | sudo bash" />
                <x-code-block title="Windows (PowerShell as Administrator)" code="irm https://app.atglance.live/console/atglance-installer.ps1 | iex" />
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <x-button :href="route('dashboard.install', ['console'])">Guided install <x-glyph name="arrow-right" /></x-button>
                <x-button href="https://app.atglance.live/console/" variant="white">All console downloads <x-glyph name="external-link" :size="14" /></x-button>
                <x-button :href="route('pricing')" variant="white">Pricing</x-button>
            </div>
        </section>
        @endif
    </div>
</x-layouts.site>
