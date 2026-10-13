<x-layouts.site title="Security">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Security" title="Quiet by default. Auditable by design." large>
            AtGlance is engineered for organisations whose first question is “where does the data live?” — and whose second is “who can change what?”
        </x-page-header>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <x-feature icon="lock" title="Self-hosted, by design">The Console runs entirely inside your boundary. There is no telemetry to atglance.live.</x-feature>
            <x-feature icon="key-round" title="PAT tokens with scopes">Each CLI host carries a Personal Access Token. Tokens are short-lived, revocable, scoped per system.</x-feature>
            <x-feature icon="shield-check" title="RBAC at the route layer">auth.session + auth.pat + admin.role middleware composed declaratively. Policy enforcement is impossible to forget.</x-feature>
            <x-feature icon="file-lock" title="Configuration backups, immutable">Every imported configuration is hashed and versioned. File storage can be local or S3 with object-level locks.</x-feature>
            <x-feature icon="database" title="Database circuit breaker">When the database is unhealthy, mutations buffer to the cache. Replays are idempotent. No silent data loss.</x-feature>
            <x-feature icon="activity" title="Auditable everything">register / deregister / reactivate / config-import all write to the activity log with operator and timestamp.</x-feature>
        </div>

        <section class="mt-20">
            <x-section-heading eyebrow="RBAC" title="Three roles. Strict separation." />
            <x-rbac-table class="mt-8" />
        </section>
    </div>
</x-layouts.site>
