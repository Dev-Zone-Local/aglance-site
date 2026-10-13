{{-- Guest layout from the console: dark hero on the left, 400px sticky card on the right. --}}
@props(['title', 'subtitle' => null, 'pageTitle' => null])
<x-layouts.site :title="$pageTitle ?? $title">
    <div class="grid gap-6 min-[901px]:grid-cols-[1fr_400px] min-[901px]:items-start">
        <section class="ag-hero order-2 px-6 py-8 sm:px-10 sm:py-12 min-[901px]:order-1">
            <h2 class="mb-4 text-[30px] font-medium leading-tight tracking-heading text-white sm:text-[40px]">Your operations platform,<br>inside your boundary.</h2>
            <p class="max-w-[560px] text-base leading-[1.7] text-ag-ink-soft">One account for the AtGlance CLI downloads and your self-hosted Management Console licences.</p>
            <div class="mt-8 grid gap-3 sm:grid-cols-3">
                @foreach ([['server', 'Self-hosted', 'Console runs in your VPC or on-prem.'], ['key-round', 'Licences', 'Create a licence key for your Console.'], ['shield-check', 'No telemetry', 'Nothing leaves your boundary.']] as [$icon, $t, $d])
                    <div class="rounded-2xl bg-white/[0.06] p-4">
                        <span class="mb-3 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/[0.12] text-ag-mint"><x-glyph :name="$icon" :size="13" /></span>
                        <div class="text-sm font-medium text-white">{{ $t }}</div>
                        <div class="mt-1 text-[13px] leading-relaxed text-ag-ink-soft">{{ $d }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="order-1 rounded-card bg-white p-7 shadow-ag min-[901px]:sticky min-[901px]:top-6 min-[901px]:order-2">
            <h1 class="text-[26px] font-medium tracking-heading text-ag-ink">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mb-6 mt-1 text-sm text-ag-subtle">{{ $subtitle }}</p>
            @else
                <div class="mb-6"></div>
            @endif
            {{ $slot }}
        </div>
    </div>
</x-layouts.site>
