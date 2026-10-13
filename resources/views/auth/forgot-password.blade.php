<x-auth-shell title="Reset your password" subtitle="We'll email you a link to set a new password." page-title="Forgot password">
    @if (session('sent'))
        <x-alert variant="success" icon="mail-check">
            {{ session('sent') }} The link expires in 60 minutes; check your spam folder too.
        </x-alert>
    @else
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-input name="email" type="email" label="Email" required autocomplete="email" placeholder="you@company.com" autofocus />
            <x-button class="w-full">Send reset link</x-button>
        </form>
    @endif

    <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-ag-teal-text hover:underline">
        <x-glyph name="arrow-left" :size="14" /> Back to sign in
    </a>
</x-auth-shell>
