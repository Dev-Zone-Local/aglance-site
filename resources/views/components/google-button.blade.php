{{--
    "Continue with Google". Shown only when Google sign-in is configured in the admin panel.
    Placed above <x-github-button>, which draws the "or" divider; without GitHub we draw it here.
--}}
@if (\App\Support\GoogleOAuth::enabled())
    <a href="{{ route('auth.google') }}" class="mb-3 inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-white px-[18px] py-2.5 text-sm font-medium text-ag-ink shadow-ag ring-1 ring-ag-line transition-colors hover:bg-ag-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ag-teal">
        <svg width="16" height="16" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
        {{ $slot }}
    </a>
    @unless (\App\Support\GithubOAuth::enabled())
        <div class="mb-5 mt-2 flex items-center gap-3" aria-hidden="true">
            <div class="h-px flex-1 bg-ag-line"></div>
            <span class="text-xs text-ag-muted">or</span>
            <div class="h-px flex-1 bg-ag-line"></div>
        </div>
    @endunless
@endif
