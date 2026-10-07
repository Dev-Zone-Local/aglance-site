<x-layouts.site title="Pricing">
    <div class="mx-auto max-w-7xl px-1 py-10 sm:px-4">
        <div class="text-center">
            <div class="mb-4 text-xs font-medium text-ag-teal-text">Pricing</div>
            <h1 class="mx-auto mb-6 max-w-4xl text-4xl font-medium leading-[1.05] tracking-heading text-ag-ink sm:text-5xl lg:text-6xl">Free. Self-hosted. Forever.</h1>
            <p class="mx-auto max-w-2xl text-lg leading-relaxed text-ag-subtle">AtGlance is free to use inside your own boundary. Enterprise plans add SLA-backed support and architecture reviews — talk to us when you need them.</p>
        </div>

        <div class="mx-auto mt-16 grid max-w-5xl gap-6 md:grid-cols-2">
            @forelse ($plans as $plan)
                <div @class([
                    'relative flex flex-col rounded-card bg-white p-7',
                    'shadow-ag-strong ring-2 ring-ag-mint' => $plan->highlighted,
                    'shadow-ag' => ! $plan->highlighted,
                ])>
                    @if ($plan->highlighted)
                        <div class="absolute -top-3 left-7 rounded-full bg-ag-gradient px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.2em] text-ag-ink">Most popular</div>
                    @endif
                    <h2 class="mb-3 text-xs font-medium text-ag-teal-text">{{ $plan->name }}</h2>
                    <div class="mb-3 flex items-baseline gap-2">
                        <span class="text-5xl font-medium tracking-heading text-ag-ink">{{ $plan->price }}</span>
                        @if ($plan->period)<span class="text-sm text-ag-muted">{{ $plan->period }}</span>@endif
                    </div>
                    <p class="mb-6 text-sm leading-relaxed text-ag-subtle">{{ $plan->description }}</p>
                    <ul class="mb-7 space-y-3">
                        @foreach ($plan->features ?? [] as $feature)
                            <li class="flex items-start gap-2.5 text-sm text-ag-ink">
                                <x-glyph name="check" class="mt-0.5 shrink-0 text-ag-success" /> <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <x-button :href="$plan->highlighted ? route('register') : route('contact')" :variant="$plan->highlighted ? 'primary' : 'ghost'" class="mt-auto w-full">
                        {{ $plan->cta }} <x-glyph name="arrow-right" :size="14" />
                    </x-button>
                </div>
            @empty
                <p class="text-center text-ag-subtle md:col-span-2">Plans are not published yet. <a href="{{ route('contact') }}" class="ag-link">Contact us</a>.</p>
            @endforelse
        </div>
    </div>
</x-layouts.site>
