{{--
    Product showcase (hero, feature sections, feature cards, gallery) from Admin → Product pages.
    Variables: $page (App\Support\ProductShowcase::get), $product ('console' | 'cli'), $latest (release or null).
--}}
@php
    $hero = $page['hero'];
    $terminal = $product === 'cli';
    $features = array_values(array_filter($page['features'] ?? [], fn ($f) => $f['visible'] ?? true));
    $link = fn (?string $url) => blank($url) ? null : (str_starts_with($url, '#') || str_starts_with($url, 'http') ? $url : url($url));
    $anchor = fn (array $f, int $i) => \Illuminate\Support\Str::slug($f['eyebrow'] ?? '') ?: 'feature-'.($i + 1);
@endphp

<section class="ag-hero overflow-hidden px-4 pb-8 pt-10 sm:px-12 sm:pb-12 sm:pt-14">
    <div class="mx-auto max-w-3xl text-center">
        @if (filled($hero['badge'] ?? null))
            <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-ag-mint">
                <x-glyph :name="$terminal ? 'terminal' : 'server'" :size="12" /> {{ $hero['badge'] }}
                @if ($latest)<span class="text-white/50">· v{{ $latest['version'] }}</span>@endif
            </div>
        @endif
        <h1 class="text-[34px] font-medium leading-[1.08] tracking-heading text-white sm:text-5xl lg:text-[56px]">{{ $hero['title'] }}</h1>
        @if (filled($hero['subtitle'] ?? null))
            <p class="mx-auto mt-5 max-w-2xl text-base leading-[1.7] text-ag-ink-soft sm:text-lg">{{ $hero['subtitle'] }}</p>
        @endif
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if (filled($hero['primary_label'] ?? null))
                <x-button :href="$link($hero['primary_url'] ?? null) ?? route('dashboard.install', [$product])">{{ $hero['primary_label'] }} <x-glyph name="arrow-right" /></x-button>
            @endif
            @if (filled($hero['secondary_label'] ?? null))
                <x-button :href="$link($hero['secondary_url'] ?? null) ?? '#features'" variant="white">{{ $hero['secondary_label'] }}</x-button>
            @endif
        </div>
        @if (! empty($hero['ticks']))
            <div class="mt-6 flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs text-white/60">
                @foreach ($hero['ticks'] as $tick)
                    <span class="inline-flex items-center gap-1.5"><x-glyph name="check" :size="12" class="text-ag-mint" /> {{ $tick }}</span>
                @endforeach
            </div>
        @endif
    </div>
    <x-screenshot-slot class="mx-auto mt-12 max-w-5xl" dark :terminal="$terminal"
        :image="$hero['image'] ?? null" :alt="$hero['title']" :path="$hero['capture_path'] ?? null" :hint="$hero['capture_hint'] ?? null"
        :icon="$terminal ? 'terminal' : 'layout-dashboard'" />
</section>

<x-latest-release :product="$product" :release="$latest" class="mt-8" />

@if ($features)
    <section id="features" class="mt-20 scroll-mt-24">
        <x-section-heading :eyebrow="$page['features_heading']['eyebrow'] ?? null" :title="$page['features_heading']['title'] ?? 'Features'" center
            :sub="$page['features_heading']['sub'] ?? null" />
        <nav class="mt-8 flex flex-wrap justify-center gap-2" aria-label="Features">
            @foreach ($features as $i => $f)
                <a href="#{{ $anchor($f, $i) }}" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-ag-ink shadow-ag transition-colors hover:text-ag-teal-text">
                    <x-glyph :name="$f['icon'] ?? 'check'" :size="14" class="text-ag-teal" /> {{ $f['eyebrow'] }}
                </a>
            @endforeach
        </nav>
    </section>

    <div class="mt-16 space-y-24">
        @foreach ($features as $i => $f)
            <section id="{{ $anchor($f, $i) }}" @class([
                'grid scroll-mt-24 items-center gap-10 lg:gap-14',
                'lg:grid-cols-[0.85fr_1.15fr]' => $i % 2 === 0,
                'lg:grid-cols-[1.15fr_0.85fr]' => $i % 2 === 1,
            ])>
                <div @class(['lg:order-2' => $i % 2 === 1])>
                    <div class="mb-3 inline-flex items-center gap-2 text-xs font-medium text-ag-teal-text">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-ag-ink text-white"><x-glyph :name="$f['icon'] ?? 'check'" :size="14" /></span>
                        {{ $f['eyebrow'] }}
                    </div>
                    <h2 class="text-[26px] font-medium leading-tight tracking-heading text-ag-ink sm:text-[32px]">{{ $f['title'] }}</h2>
                    @if (filled($f['text'] ?? null))
                        <p class="mt-4 text-[15px] leading-relaxed text-ag-subtle">{{ $f['text'] }}</p>
                    @endif
                    @if (! empty($f['bullets']))
                        <ul class="mt-6 space-y-3">
                            @foreach ($f['bullets'] as $b)
                                <li class="flex gap-3 text-[15px] text-ag-ink">
                                    <span class="mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-ag-success-soft text-ag-success-text"><x-glyph name="check" :size="12" /></span>
                                    {{ $b }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <x-screenshot-slot :class="$i % 2 === 1 ? 'lg:order-1' : ''" :terminal="$terminal"
                    :image="$f['image'] ?? null" :alt="$f['title']" :caption="$f['caption'] ?? null"
                    :path="$f['capture_path'] ?? null" :hint="$f['capture_hint'] ?? null" :icon="$f['icon'] ?? 'monitor'" />
            </section>
        @endforeach
    </div>
@endif

@if (! empty($page['extras']))
    <section class="mt-24">
        <x-section-heading :eyebrow="$page['extras_heading']['eyebrow'] ?? null" :title="$page['extras_heading']['title'] ?? 'And more'" />
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($page['extras'] as $e)
                <x-feature :icon="$e['icon'] ?? null" :title="$e['title'] ?? ''">{{ $e['text'] ?? '' }}</x-feature>
            @endforeach
        </div>
    </section>
@endif

@if (collect($page['gallery'] ?? [])->contains(fn ($g) => filled($g['image'] ?? null)))
    <section class="mt-20">
        <x-section-heading eyebrow="Gallery" title="More screenshots" />
        <x-screenshot-gallery :screenshots="$page['gallery']" class="mt-8" />
    </section>
@endif
