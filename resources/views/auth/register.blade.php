<x-auth-shell title="Create your free account" subtitle="Get the AtGlance CLI and self-hosted Console downloads." page-title="Sign up">
    <x-github-button>Sign up with GitHub</x-github-button>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Name" autocomplete="name" placeholder="Jane Doe" maxlength="255" />
        <x-input name="email" type="email" label="Email" required autocomplete="email" placeholder="you@company.com" />
        <x-input name="password" type="password" label="Password" required minlength="6" autocomplete="new-password" placeholder="At least 6 characters" />
        <x-input name="password_confirmation" type="password" label="Confirm password" required minlength="6" autocomplete="new-password" placeholder="Repeat your password" />
        <x-button class="w-full">Create account <x-glyph name="arrow-right" :size="14" /></x-button>
    </form>

    <p class="mt-6 text-sm text-ag-subtle">
        Already have an account? <a href="{{ route('login') }}" class="ag-link">Sign in</a>
    </p>
</x-auth-shell>
