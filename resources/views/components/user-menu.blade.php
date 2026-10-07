{{-- User chip + drop-down: white chip, 36px ink avatar, 16px-radius menu. --}}
@props(['showDashboard' => false])
@php($user = auth()->user())
@php($item = 'flex w-full items-center gap-2.5 rounded-[10px] px-3 py-2.5 text-sm text-ag-ink transition-colors hover:bg-ag-surface focus-visible:bg-ag-surface focus-visible:outline-none')
<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="menu"
        class="flex items-center gap-2.5 rounded-2xl bg-white py-1.5 pl-1.5 pr-3 shadow-ag transition hover:shadow-ag-strong focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
        @if ($user->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="" class="h-9 w-9 rounded-xl object-cover">
        @else
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-ag-ink text-sm font-medium text-ag-mint">{{ mb_strtoupper(mb_substr(trim($user->name ?: $user->email), 0, 1)) }}</span>
        @endif
        <span class="hidden text-left min-[1281px]:block">
            <span class="block max-w-[160px] truncate text-sm font-medium text-ag-ink">{{ $user->name }}</span>
            <span class="block max-w-[160px] truncate text-[11px] text-ag-muted">{{ $user->email }}</span>
        </span>
        <span class="sr-only">Account menu</span>
        <x-glyph name="chevron-down" :size="14" class="text-ag-muted transition-transform" x-bind:class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.150ms role="menu"
        class="absolute right-0 top-[calc(100%+8px)] z-50 w-60 rounded-2xl bg-white p-2 shadow-ag-strong">
        <div class="px-3 pb-2 pt-1 min-[1281px]:hidden">
            <div class="truncate text-sm font-medium text-ag-ink">{{ $user->name }}</div>
            <div class="truncate text-[11px] text-ag-muted">{{ $user->email }}</div>
        </div>
        @if ($showDashboard)
            <a href="{{ route('dashboard') }}" class="{{ $item }}" role="menuitem"><x-glyph name="layout-dashboard" :size="15" class="text-ag-teal" /> Dashboard</a>
        @endif
        <a href="{{ route('dashboard.profile') }}" class="{{ $item }}" role="menuitem"><x-glyph name="user-round" :size="15" class="text-ag-teal" /> Profile</a>
        @if ($user->role === 'admin')
            <a href="/admin" class="{{ $item }}" role="menuitem"><x-glyph name="shield" :size="15" class="text-ag-teal" /> Admin panel</a>
        @endif
        @unless ($showDashboard)
            <a href="{{ route('home') }}" class="{{ $item }}" role="menuitem"><x-glyph name="globe" :size="15" class="text-ag-teal" /> Back to website</a>
        @endunless
        <div class="my-1.5 h-px bg-ag-line"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="{{ $item }} text-ag-danger-text" role="menuitem"><x-glyph name="log-out" :size="15" /> Sign out</button>
        </form>
    </div>
</div>
