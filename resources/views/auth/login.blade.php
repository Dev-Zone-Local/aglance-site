<x-auth-shell title="Welcome back" subtitle="Sign in to access your downloads and licences." page-title="Sign in">
    <x-github-button>Continue with GitHub</x-github-button>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" label="Email" required autocomplete="email" placeholder="you@company.com" autofocus />
        <x-input name="password" type="password" label="Password" required autocomplete="current-password" placeholder="••••••••">
            <x-slot:label-aside>
                <a href="{{ route('password.request') }}" class="mb-1.5 text-[13px] font-medium text-ag-teal-text hover:underline">Forgot password?</a>
            </x-slot:label-aside>
        </x-input>
        <label class="flex items-center gap-2 text-sm text-ag-subtle">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-[#2CB7D9]"> Keep me signed in
        </label>
        <x-button class="w-full">Sign in <x-glyph name="arrow-right" :size="14" /></x-button>
    </form>

    <p class="mt-6 text-sm text-ag-subtle">
        New to AtGlance? <a href="{{ route('register') }}" class="ag-link">Create a free account</a>
    </p>
</x-auth-shell>
