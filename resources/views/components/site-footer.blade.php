@php
    $links = [
        'about' => 'About', 'contact' => 'Contact', 'faq' => 'FAQ', 'docs' => 'Docs', 'releases' => 'Release notes',
        'security' => 'Security', 'terms' => 'Terms', 'privacy' => 'Privacy',
    ];
    $contact = once(fn () => \App\Models\Setting::get(\App\Models\Setting::CONTACT));
    $socials = ($contact['show_social_in_footer'] ?? true)
        ? array_filter(\App\Filament\Pages\ContactSettings::SOCIALS, fn ($s, $key) => filled($contact[$key] ?? null), ARRAY_FILTER_USE_BOTH)
        : [];
@endphp
<footer class="mt-16 py-6 text-center text-xs text-ag-muted">
    <nav class="mb-2" aria-label="Footer">
        @foreach ($links as $route => $label)
            @unless ($loop->first)<span class="mx-2" aria-hidden="true">|</span>@endunless
            <a href="{{ route($route) }}" class="text-ag-ink transition-colors hover:text-ag-teal-text">{{ $label }}</a>
        @endforeach
    </nav>
    @if ($socials)
        <div class="mb-3 flex justify-center gap-3">
            @foreach ($socials as $key => [$label, $icon])
                <a href="{{ $contact[$key] }}" target="_blank" rel="noreferrer" class="text-ag-subtle transition-colors hover:text-ag-teal-text" aria-label="{{ $label }}" title="{{ $label }}">
                    <x-glyph :name="$icon" :size="16" />
                </a>
            @endforeach
        </div>
    @endif
    <div>© {{ date('Y') }} AtGlance · Self-hosted operations for SREs, inside your boundary.</div>
</footer>
