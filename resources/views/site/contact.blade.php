@php
    use App\Filament\Pages\ContactSettings;

    $emails = [
        ['mail', 'General', $contact['email'] ?? null],
        ['message-square', 'Sales', $contact['sales_email'] ?? null],
        ['headphones', 'Support', $contact['support_email'] ?? null],
        ['shield', 'Security reports', $contact['security_email'] ?? null],
        ['file-lock', 'Billing', $contact['billing_email'] ?? null],
        ['globe', 'Press & partnerships', $contact['press_email'] ?? null],
    ];
    $emails = array_filter($emails, fn ($e) => filled($e[2]));
    $socials = array_filter(ContactSettings::SOCIALS, fn ($s, $key) => filled($contact[$key] ?? null), ARRAY_FILTER_USE_BOTH);
    $showAddress = ($contact['show_address'] ?? true) && filled($contact['address'] ?? null);
@endphp
<x-layouts.site title="Contact">
    <div class="mx-auto max-w-5xl px-1 py-10 sm:px-4">
        <x-page-header eyebrow="Contact" :title="$contact['headline'] ?? ContactSettings::DEFAULTS['headline']" large>
            {{ $contact['intro'] ?? ContactSettings::DEFAULTS['intro'] }}
        </x-page-header>

        @if (filled($contact['response_time'] ?? null))
            <x-badge variant="info" class="mt-2"><x-glyph name="clock" :size="12" /> {{ $contact['response_time'] }}</x-badge>
        @endif

        @if ($emails)
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($emails as [$icon, $label, $value])
                    <a href="mailto:{{ $value }}" class="lift block rounded-card bg-white p-6 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                        <x-glyph :name="$icon" :size="18" class="mb-4 text-ag-teal" />
                        <div class="mb-1 font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">{{ $label }}</div>
                        <div class="break-all text-ag-ink">{{ $value }}</div>
                    </a>
                @endforeach
            </div>
        @endif

        @if (filled($contact['phone'] ?? null) || filled($contact['support_hours'] ?? null) || $showAddress)
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                @if (filled($contact['phone'] ?? null) || filled($contact['support_hours'] ?? null))
                    <x-card class="p-6">
                        @if (filled($contact['phone'] ?? null))
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="flex items-center gap-3 hover:text-ag-teal-text">
                                <x-glyph name="phone" :size="18" class="text-ag-teal" />
                                <span>
                                    <span class="block font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">Phone</span>
                                    <span class="block text-ag-ink">{{ $contact['phone'] }}</span>
                                </span>
                            </a>
                        @endif
                        @if (filled($contact['support_hours'] ?? null))
                            <div @class(['flex items-center gap-3', 'mt-5' => filled($contact['phone'] ?? null)])>
                                <x-glyph name="clock" :size="18" class="text-ag-teal" />
                                <span>
                                    <span class="block font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">Support hours</span>
                                    <span class="block text-ag-ink">{{ $contact['support_hours'] }}</span>
                                </span>
                            </div>
                        @endif
                    </x-card>
                @endif

                @if ($showAddress)
                    <x-card class="p-6">
                        <div class="flex items-start gap-3">
                            <x-glyph name="map-pin" :size="18" class="mt-0.5 text-ag-teal" />
                            <div>
                                <div class="font-mono text-[11px] uppercase tracking-[0.16em] text-ag-subtle">Office</div>
                                @if (filled($contact['company_name'] ?? null))
                                    <div class="font-medium text-ag-ink">{{ $contact['company_name'] }}</div>
                                @endif
                                <div class="whitespace-pre-line text-ag-ink">{{ $contact['address'] }}</div>
                                @if (filled($contact['map_url'] ?? null))
                                    <a href="{{ $contact['map_url'] }}" target="_blank" rel="noreferrer" class="mt-2 inline-flex items-center gap-1.5 text-sm text-ag-teal-text hover:underline">Open map <x-glyph name="external-link" :size="12" /></a>
                                @endif
                            </div>
                        </div>
                    </x-card>
                @endif
            </div>
        @endif

        @if ($socials)
            <div class="mt-10 flex flex-wrap gap-3">
                @foreach ($socials as $key => [$label, $icon])
                    <a href="{{ $contact[$key] }}" target="_blank" rel="noreferrer" class="lift inline-flex items-center gap-3 rounded-card bg-white px-5 py-4 shadow-ag focus-visible:outline focus-visible:outline-2 focus-visible:outline-ag-teal">
                        <x-glyph :name="$icon" :size="18" class="text-ag-teal" />
                        <span class="text-ag-ink">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.site>
