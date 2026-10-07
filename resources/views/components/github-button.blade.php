{{-- "Continue with GitHub" + divider. Shown only when GitHub sign-in is configured in the admin panel. --}}
@if (\App\Support\GithubOAuth::enabled())
    <a href="{{ route('auth.github') }}" class="inline-flex w-full items-center justify-center gap-2.5 rounded-full bg-ag-ink px-[18px] py-2.5 text-sm font-medium text-white transition-colors hover:bg-ag-ink-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ag-teal">
        <x-glyph name="github" /> {{ $slot }}
    </a>
    <div class="my-5 flex items-center gap-3" aria-hidden="true">
        <div class="h-px flex-1 bg-ag-line"></div>
        <span class="text-xs text-ag-muted">or</span>
        <div class="h-px flex-1 bg-ag-line"></div>
    </div>
@endif
