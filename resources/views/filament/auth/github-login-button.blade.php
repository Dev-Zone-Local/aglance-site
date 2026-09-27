@if (\App\Support\GithubOAuth::enabled())
    <div class="mt-2 grid gap-y-4">
        <div class="flex items-center gap-x-3 text-sm text-gray-500 dark:text-gray-400">
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            or
            <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
        </div>

        <x-filament::button
            tag="a"
            :href="route('admin.auth.github')"
            color="gray"
            icon="heroicon-o-code-bracket"
            class="w-full"
        >
            Sign in with GitHub
        </x-filament::button>
    </div>
@endif
