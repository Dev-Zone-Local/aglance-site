<x-layouts.app title="Dashboard">
    <div class="space-y-6">
        @if ($notice)
            <x-alert :variant="$notice[0] === 'success' ? 'success' : 'danger'">{{ $notice[1] }}</x-alert>
        @endif

        <x-email-verify-banner />

        <x-hero>
            <x-slot:title>Welcome back{{ $user->name ? ', '.$user->name : '' }}.</x-slot:title>
            Run AtGlance inside your boundary: install the CLI on your hosts and the Management Console for your organization.
            <x-slot:actions>
                <x-button :href="route('dashboard.install')" size="lg">Install AtGlance <x-glyph name="arrow-right" /></x-button>
                <x-button :href="route('dashboard.licences')" size="lg" variant="white">Manage licences</x-button>
            </x-slot:actions>
        </x-hero>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                ['cpu', 'AtGlance CLI', $cliVersion ? 'v'.$cliVersion : '—', 'Latest version', route('dashboard.install', ['cli']), 'Install the CLI'],
                ['server', 'Management Console', $consoleVersion ? 'v'.$consoleVersion : '—', 'Latest version', route('dashboard.install', ['console']), 'Install the Console'],
                ['key-round', 'Licences', $licences->count().($limit !== null ? ' / '.$limit : ''), $user->planLabel().' plan', route('dashboard.licences'), $licences->isNotEmpty() ? 'View licences' : 'Create a licence'],
            ] as [$icon, $label, $value, $sub, $href, $cta])
                <a href="{{ $href }}" class="group block rounded-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                    <x-card hover class="h-full p-6">
                        <div class="mb-4 flex items-center gap-2.5">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-ag-ink text-white"><x-glyph :name="$icon" :size="13" /></span>
                            <span class="text-[13px] font-medium text-ag-subtle">{{ $label }}</span>
                        </div>
                        <div class="text-[36px] font-normal leading-[1.1] tracking-heading text-ag-ink">{{ $value }}</div>
                        <div class="mt-1.5 text-xs text-ag-muted">{{ $sub }}</div>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text transition-all group-hover:gap-2.5">{{ $cta }} <x-glyph name="arrow-right" :size="14" /></span>
                    </x-card>
                </a>
            @endforeach
        </div>

        <a href="{{ route('releases') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
            <x-glyph name="bell" :size="14" /> What's new: read the release notes <x-glyph name="arrow-right" :size="14" />
        </a>

        @if ($licences->isNotEmpty())
            <x-card class="p-6">
                <h2 class="mb-3 text-[15px] font-medium text-ag-ink">Your licences</h2>
                <ul class="divide-y divide-ag-line">
                    @foreach ($licences->take(3) as $licence)
                        <li class="flex flex-wrap items-center gap-3 py-3 text-sm">
                            <span class="font-mono text-ag-ink">{{ $licence->name }}</span>
                            <x-licence-status :status="$licence->status()" />
                            @if ($licence->activation?->org_name)
                                <span class="text-ag-subtle">{{ $licence->activation->org_name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <a href="{{ route('docs.show', 'quickstart') }}" class="group block rounded-card">
            <x-card flat class="flex items-center gap-3 p-5">
                <x-glyph name="book-open" class="text-ag-teal" />
                <span class="text-sm text-ag-ink">New to AtGlance? Read the 5-minute quickstart.</span>
                <x-glyph name="arrow-right" :size="14" class="ml-auto text-ag-teal transition-transform group-hover:translate-x-1" />
            </x-card>
        </a>
    </div>
</x-layouts.app>
