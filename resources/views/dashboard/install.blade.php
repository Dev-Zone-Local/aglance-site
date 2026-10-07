@php($stepper = ['Choose product', 'Choose where', 'Install'])
<x-layouts.app title="Install">

    @if (! $product)
        {{-- Step 1: product --}}
        <x-page-header eyebrow="Install" title="What do you want to install?">
            Pick a product. You can come back here any time to update or install on another machine.
        </x-page-header>
        @include('dashboard.partials.stepper', ['steps' => $stepper, 'current' => 0])
        <div class="grid gap-4 md:grid-cols-2">
            @foreach (\App\Support\InstallCatalog::PRODUCTS as $id => $p)
                @include('dashboard.partials.choice', [
                    'href' => route('dashboard.install', [$id]),
                    'icon' => $p['icon'], 'title' => $p['name'], 'hint' => $p['tagline'],
                    'badge' => $versions[$id] ? 'v'.$versions[$id] : null, 'badgeVariant' => 'success',
                ])
            @endforeach
        </div>
    @elseif (! $target)
        {{-- Step 2: target --}}
        <a href="{{ route('dashboard.install') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text"><x-glyph name="arrow-left" :size="14" /> All products</a>
        <x-page-header :eyebrow="$product['name']" title="Where do you want to run it?" />
        @include('dashboard.partials.stepper', ['steps' => $stepper, 'current' => 1])
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($product['targets'] as $id => $t)
                @include('dashboard.partials.choice', [
                    'href' => $t['status'] === 'ready' ? route('dashboard.install', [$productId, $id]) : null,
                    'icon' => $t['icon'], 'title' => $t['name'], 'hint' => $t['hint'],
                    'badge' => $t['status'] === 'ready' ? null : 'Coming soon', 'badgeVariant' => 'default',
                ])
            @endforeach
        </div>
    @else
        {{-- Step 3: guided steps --}}
        <a href="{{ route('dashboard.install', [$productId]) }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text"><x-glyph name="arrow-left" :size="14" /> Change platform</a>
        <x-page-header :eyebrow="$product['name'].($versions[$productId] ? ' · v'.$versions[$productId] : '')"
            :title="($mode === 'update' ? 'Update on ' : 'Install on ').$target['name']" />
        @include('dashboard.partials.stepper', ['steps' => $stepper, 'current' => 2])

        <nav class="mb-5 inline-flex rounded-full bg-white p-[5px] shadow-ag" aria-label="Install or update">
            @foreach (['install' => 'New install', 'update' => 'Update existing'] as $key => $label)
                <a href="{{ route('dashboard.install', [$productId, $targetId]).($key === 'update' ? '?mode=update' : '') }}"
                    @if ($mode === $key) aria-current="page" @endif
                    @class([
                        'rounded-full px-[18px] py-[9px] text-sm font-medium transition',
                        'bg-ag-gradient text-ag-ink shadow-ag-pill' => $mode === $key,
                        'text-ag-subtle hover:bg-ag-surface hover:text-ag-ink' => $mode !== $key,
                    ])>{{ $label }}</a>
            @endforeach
        </nav>

        @if (empty($steps))
            <x-card class="text-sm text-ag-subtle">Instructions for this platform are coming soon.</x-card>
        @else
            <ol class="space-y-4">
                @foreach ($steps as $i => $s)
                    <li class="rise" style="animation-delay: {{ $i * 40 }}ms">
                        <x-card class="p-6">
                            <div class="flex gap-4">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ag-ink text-sm font-medium text-ag-mint" aria-hidden="true">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1 space-y-3">
                                    <h2 class="pt-1.5 text-[17px] font-medium tracking-heading text-ag-ink">{{ $s['title'] }}</h2>
                                    @if (! empty($s['text']))
                                        <p class="text-sm leading-relaxed text-ag-subtle">{{ $s['text'] }}</p>
                                    @endif
                                    @if (! empty($s['checksum']))
                                        <div>
                                            <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-ag-subtle"><x-glyph name="shield-check" :size="13" class="text-ag-success" /> SHA-256</div>
                                            <code class="block break-all rounded-input bg-ag-surface px-3.5 py-2.5 font-mono text-xs text-ag-ink">{{ $s['checksum'] }}</code>
                                        </div>
                                    @endif
                                    @if (! empty($s['code']))
                                        <x-code-block :code="$s['code']" />
                                    @endif
                                    @if (! empty($s['markdown']))
                                        <x-markdown :source="$s['markdown']" />
                                    @endif
                                    @if (! empty($s['action']) || ! empty($s['link']))
                                        <div class="flex flex-wrap gap-2">
                                            @if (! empty($s['action']))
                                                <x-button :href="$s['action']['href']" target="_blank" rel="noreferrer"><x-glyph name="download" :size="14" /> {{ $s['action']['label'] }} <x-glyph name="external-link" :size="12" /></x-button>
                                            @endif
                                            @if (! empty($s['link']))
                                                <x-button :href="$s['link']['href']" variant="ghost">{{ $s['link']['label'] }} <x-glyph name="arrow-right" :size="14" /></x-button>
                                            @endif
                                        </div>
                                    @endif
                                    @if (in_array($s['key'], ['download', 'update-script'], true) && empty($s['action']))
                                        <p class="text-xs text-ag-muted">Download link not published yet.</p>
                                    @endif
                                </div>
                            </div>
                        </x-card>
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</x-layouts.app>
