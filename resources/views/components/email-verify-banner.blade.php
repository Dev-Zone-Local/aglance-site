{{-- Reminder while the signed-in user's email is not verified. --}}
@php($user = auth()->user())
@if ($user && ! $user->hasVerifiedEmail())
    <div class="mb-6 flex flex-col gap-3 rounded-input bg-ag-warning-soft px-5 py-4 sm:flex-row sm:items-center" role="status">
        <x-glyph name="mail-warning" :size="18" class="shrink-0 text-ag-warning" />
        <div class="flex-1 text-sm text-ag-warning-text">
            @if ($user->hasDeliverableEmail())
                Please verify <b>{{ $user->email }}</b>. We sent you a link; check your inbox and spam folder.
            @else
                Your GitHub account has no public email, so we cannot verify it. Add a public email on GitHub and sign in again.
            @endif
        </div>
        @if ($user->hasDeliverableEmail())
            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="rounded-full bg-white px-3.5 py-1.5 text-sm font-medium text-ag-warning-text shadow-ag hover:shadow-ag-strong">Resend email</button>
            </form>
        @endif
    </div>
@endif
