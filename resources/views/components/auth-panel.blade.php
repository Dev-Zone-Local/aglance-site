{{--
    Sign in / Sign up for guests: two header buttons and one panel under them (no separate pages).
    Opens on hover (mouse), on click or tap, from any $dispatch('open-auth', 'login'|'register'),
    and by itself after a failed sign-in or sign-up (session "auth_panel"), showing the errors.
    Alpine component: authPanel in resources/js/app.js.
--}}
@php
    $initial = session('auth_panel');
    $initial = in_array($initial, ['login', 'register'], true) ? $initial : null;
    $tabs = ['login' => 'Sign in', 'register' => 'Sign up'];
@endphp
<div class="relative flex items-center gap-2" x-data="authPanel(@js($initial))"
    x-on:open-auth.window="openFrom($event.detail)"
    x-on:keydown.escape.window="close()" x-on:click.outside="close()" x-on:mouseleave="leave()" x-on:mouseenter="stay()"
    x-on:focusin="pinned = open || pinned">
    <button type="button" x-on:mouseenter="hover('login')" x-on:click="toggle('login')"
        :aria-expanded="(open && mode === 'login').toString()" aria-controls="auth-panel"
        class="hidden rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink transition-colors hover:bg-ag-line focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal min-[901px]:inline-flex">Sign in</button>
    <button type="button" x-on:mouseenter="hover('register')" x-on:click="toggle('register')"
        :aria-expanded="(open && mode === 'register').toString()" aria-controls="auth-panel"
        class="inline-flex whitespace-nowrap rounded-full bg-ag-gradient px-[18px] py-2.5 text-sm font-medium text-ag-ink transition hover:bg-ag-gradient-hover hover:shadow-ag-glow focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">Sign up free</button>

    <div id="auth-panel" x-show="open" x-cloak role="dialog" aria-modal="false" :aria-label="mode === 'login' ? 'Sign in' : 'Sign up'"
        x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        x-on:input="pinned = true"
        class="absolute right-0 top-[calc(100%+10px)] z-50 w-[min(400px,calc(100vw-32px))] rounded-[22px] bg-white p-5 shadow-ag-strong ring-1 ring-black/5 motion-reduce:transition-none">
        {{-- Pointer bridge so moving down from the buttons does not close the panel. --}}
        <span class="absolute -top-3 left-0 right-0 h-3" aria-hidden="true"></span>

        <div class="flex items-center gap-1 rounded-full bg-ag-surface p-1" role="tablist" aria-label="Account">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" id="auth-tab-{{ $key }}" aria-controls="auth-{{ $key }}" x-on:click="show('{{ $key }}')"
                    :aria-selected="(mode === '{{ $key }}').toString()"
                    :class="mode === '{{ $key }}' ? 'bg-white text-ag-ink shadow-ag' : 'text-ag-subtle hover:text-ag-ink'"
                    class="flex-1 rounded-full px-3 py-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">{{ $label }}</button>
            @endforeach
            <button type="button" x-on:click="close()" class="ml-1 inline-flex h-8 w-8 items-center justify-center rounded-full text-ag-subtle hover:bg-white hover:text-ag-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal" aria-label="Close">
                <x-glyph name="x" :size="14" />
            </button>
        </div>

        {{-- Sign in --}}
        <div id="auth-login" role="tabpanel" aria-labelledby="auth-tab-login" x-ref="login" x-show="mode === 'login'" class="mt-4">
            <x-google-button>Continue with Google</x-google-button>
            <x-github-button>Continue with GitHub</x-github-button>
            <form method="POST" action="{{ route('login') }}" class="space-y-3.5">
                @csrf
                <x-input name="email" id="login-email" type="email" label="Email" required autocomplete="email" placeholder="you@company.com" />
                <x-input name="password" id="login-password" type="password" label="Password" required autocomplete="current-password" placeholder="••••••••">
                    <x-slot:label-aside>
                        <a href="{{ route('password.request') }}" class="mb-1.5 text-[13px] font-medium text-ag-teal-text hover:underline">Forgot password?</a>
                    </x-slot:label-aside>
                </x-input>
                <label class="flex items-center gap-2 text-sm text-ag-subtle">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-[#2CB7D9]"> Keep me signed in
                </label>
                <x-recaptcha action="login" />
                <x-button class="w-full">Sign in <x-glyph name="arrow-right" :size="14" /></x-button>
            </form>
            <p class="mt-4 text-center text-sm text-ag-subtle">New to AtGlance? <button type="button" class="ag-link" x-on:click="show('register')">Create a free account</button></p>
        </div>

        {{-- Sign up --}}
        <div id="auth-register" role="tabpanel" aria-labelledby="auth-tab-register" x-ref="register" x-show="mode === 'register'" x-cloak class="mt-4">
            <x-google-button>Sign up with Google</x-google-button>
            <x-github-button>Sign up with GitHub</x-github-button>
            <form method="POST" action="{{ route('register') }}" class="space-y-3.5">
                @csrf
                <x-input name="name" id="register-name" label="Name" autocomplete="name" placeholder="Jane Doe" maxlength="255" />
                <x-input name="email" id="register-email" type="email" label="Email" required autocomplete="email" placeholder="you@company.com" />
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <x-input name="password" id="register-password" type="password" label="Password" required minlength="6" autocomplete="new-password" placeholder="6+ characters" />
                    <x-input name="password_confirmation" id="register-password-confirmation" type="password" label="Confirm" required minlength="6" autocomplete="new-password" placeholder="Repeat it" />
                </div>
                <x-recaptcha action="register" />
                <x-button class="w-full">Create account <x-glyph name="arrow-right" :size="14" /></x-button>
            </form>
            <p class="mt-4 text-center text-sm text-ag-subtle">Already have an account? <button type="button" class="ag-link" x-on:click="show('login')">Sign in</button></p>
        </div>
    </div>
</div>
