@php
    $nav = [
        'product' => 'Product', 'cli' => 'CLI', 'console' => 'Console', 'architecture' => 'Architecture',
        'use-cases' => 'Use cases', 'security' => 'Security', 'pricing' => 'Pricing', 'docs' => 'Docs',
    ];
    $isActive = fn (string $route) => request()->routeIs($route) || request()->routeIs($route.'.*');
@endphp
<header class="relative z-40 mb-6" x-data="{ open: false }" x-on:resize.window="if (window.innerWidth > 900) open = false">
    <div class="flex items-center gap-4">
        <x-logo />

        <nav class="mx-auto hidden items-center gap-1 rounded-full bg-white p-[5px] shadow-ag min-[901px]:flex" aria-label="Main">
            @foreach ($nav as $route => $label)
                <a href="{{ route($route) }}" @if ($isActive($route)) aria-current="page" @endif
                    @class([
                        'whitespace-nowrap rounded-full px-4 py-[9px] text-sm font-medium transition-colors duration-200 max-[1560px]:px-[13px] max-[1180px]:px-2.5 max-[1180px]:text-[13px]',
                        'bg-ag-gradient text-ag-ink shadow-ag-pill' => $isActive($route),
                        'text-ag-subtle hover:bg-ag-surface hover:text-ag-ink' => ! $isActive($route),
                    ])>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2 min-[901px]:ml-0">
            @auth
                <x-user-menu :show-dashboard="true" />
            @else
                <a href="{{ route('login') }}" class="hidden rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink transition-colors hover:bg-ag-line min-[901px]:inline-flex">Sign in</a>
                <a href="{{ route('register') }}" class="inline-flex whitespace-nowrap rounded-full bg-ag-gradient px-[18px] py-2.5 text-sm font-medium text-ag-ink transition hover:bg-ag-gradient-hover hover:shadow-ag-glow">Sign up free</a>
            @endauth
            <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-controls="mobile-nav"
                class="inline-flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white text-ag-ink shadow-ag min-[901px]:hidden">
                <span class="sr-only">Toggle navigation</span>
                <x-glyph name="menu" :size="18" x-show="!open" />
                <x-glyph name="x" :size="18" x-show="open" x-cloak />
            </button>
        </div>
    </div>

    <div id="mobile-nav" x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute left-0 right-0 top-[54px] rounded-2xl bg-white p-2 shadow-ag-strong min-[901px]:hidden">
        @foreach ($nav as $route => $label)
            <a href="{{ route($route) }}" @class([
                'block rounded-[10px] px-3 py-2.5 text-sm font-medium',
                'bg-ag-surface text-ag-ink' => $isActive($route),
                'text-ag-subtle hover:bg-ag-surface hover:text-ag-ink' => ! $isActive($route),
            ])>{{ $label }}</a>
        @endforeach
        <div class="my-2 h-px bg-ag-line"></div>
        @auth
            <a href="{{ auth()->user()->role === 'admin' ? '/admin' : route('dashboard') }}" class="block rounded-[10px] px-3 py-2.5 text-sm font-medium text-ag-ink hover:bg-ag-surface">
                {{ auth()->user()->role === 'admin' ? 'Admin' : 'Dashboard' }}
            </a>
        @else
            <a href="{{ route('login') }}" class="block rounded-[10px] px-3 py-2.5 text-sm font-medium text-ag-ink hover:bg-ag-surface">Sign in</a>
        @endauth
    </div>
</header>
