{{-- Signed-in area: account navigation and the user menu only. --}}
@props(['title' => null])
@php
    $nav = [
        ['dashboard', 'Overview', 'layout-dashboard', fn () => request()->routeIs('dashboard')],
        ['dashboard.install', 'Install', 'download', fn () => request()->routeIs('dashboard.install*')],
        ['dashboard.licences', 'Licences', 'key-round', fn () => request()->routeIs('dashboard.licences')],
    ];
@endphp
<x-layouts.base :title="$title">
    <div class="ag-frame flex flex-col">
        <header class="relative z-40 mb-6" x-data="{ open: false }">
            <div class="flex items-center gap-4">
                <x-logo />
                <nav class="mx-auto hidden items-center gap-1 rounded-full bg-white p-[5px] shadow-ag min-[761px]:flex" aria-label="Account">
                    @foreach ($nav as [$route, $label, $icon, $active])
                        <a href="{{ route($route) }}" @if ($active()) aria-current="page" @endif @class([
                            'inline-flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-[9px] text-sm font-medium transition-colors duration-200',
                            'bg-ag-gradient text-ag-ink shadow-ag-pill' => $active(),
                            'text-ag-subtle hover:bg-ag-surface hover:text-ag-ink' => ! $active(),
                        ])><x-glyph :name="$icon" :size="14" /> {{ $label }}</a>
                    @endforeach
                </nav>
                <div class="ml-auto flex items-center gap-2 min-[761px]:ml-0">
                    <x-user-menu />
                    <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-controls="app-mobile-nav"
                        class="inline-flex h-[42px] w-[42px] items-center justify-center rounded-[14px] bg-white text-ag-ink shadow-ag min-[761px]:hidden">
                        <span class="sr-only">Toggle navigation</span>
                        <x-glyph name="menu" :size="18" x-show="!open" />
                        <x-glyph name="x" :size="18" x-show="open" x-cloak />
                    </button>
                </div>
            </div>
            <div id="app-mobile-nav" x-show="open" x-cloak class="absolute left-0 right-0 top-[58px] rounded-2xl bg-white p-2 shadow-ag-strong min-[761px]:hidden">
                @foreach ($nav as [$route, $label, $icon, $active])
                    <a href="{{ route($route) }}" @class([
                        'flex items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-sm font-medium',
                        'bg-ag-surface text-ag-ink' => $active(),
                        'text-ag-subtle hover:bg-ag-surface' => ! $active(),
                    ])><x-glyph :name="$icon" :size="15" /> {{ $label }}</a>
                @endforeach
            </div>
        </header>
        <main id="main" class="flex-1">
            {{ $slot }}
        </main>
        <x-site-footer />
    </div>
</x-layouts.base>
