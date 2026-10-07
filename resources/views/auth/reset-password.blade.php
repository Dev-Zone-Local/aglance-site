@if ($token === '' || $email === '')
    <x-auth-shell title="Reset link incomplete" page-title="Reset password">
        <x-alert variant="danger">This reset link is missing information. Request a new one.</x-alert>
        <x-button :href="route('password.request')" class="mt-5 w-full">Request a new link</x-button>
    </x-auth-shell>
@else
    <x-auth-shell title="Choose a new password" :subtitle="'For '.$email" page-title="Reset password">
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            @error('token')
                <x-alert variant="danger">{{ $message }} <a href="{{ route('password.request') }}" class="font-medium underline">Request a new link</a></x-alert>
            @enderror
            <x-input name="password" type="password" label="New password" required minlength="6" autocomplete="new-password" placeholder="At least 6 characters" autofocus />
            <x-input name="password_confirmation" type="password" label="Confirm new password" required minlength="6" autocomplete="new-password" placeholder="Repeat the password" />
            <x-button class="w-full">Set new password</x-button>
        </form>
    </x-auth-shell>
@endif
