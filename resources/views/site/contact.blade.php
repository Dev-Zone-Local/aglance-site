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

        @if ($contact['show_form'] ?? true)
            @php
                $user = auth()->user();
                $topic = old('topic', isset(\App\Models\ContactMessage::TOPICS[request()->string('topic')->toString()]) ? request()->string('topic')->toString() : 'support');
                $productSel = old('product', isset(\App\Models\ContactMessage::PRODUCTS[request()->string('product')->toString()]) ? request()->string('product')->toString() : '');
            @endphp
            <section id="contact-form" class="mt-10 grid scroll-mt-24 gap-6 lg:grid-cols-[1fr_300px]">
                <x-card class="p-6 sm:p-8">
                    <h2 class="text-[22px] font-medium tracking-heading text-ag-ink">{{ $contact['form_title'] ?? ContactSettings::DEFAULTS['form_title'] }}</h2>
                    <p class="mt-1 text-sm text-ag-subtle">{{ $contact['form_intro'] ?? ContactSettings::DEFAULTS['form_intro'] }}</p>

                    @if (session('sent_reference'))
                        <div class="mt-5 flex items-start gap-3 rounded-xl bg-ag-success-soft px-4 py-3 text-sm text-ag-success-text" role="status">
                            <x-glyph name="check" :size="16" class="mt-0.5" />
                            <span>Message sent. Your reference is <strong>{{ session('sent_reference') }}</strong>. We also emailed you a copy.</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="mt-6 grid gap-4 sm:grid-cols-2" novalidate>
                        @csrf
                        {{-- Honeypot for bots; hidden from people and screen readers. --}}
                        <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                        <x-input name="name" label="Your name" :value="$user?->name" required maxlength="100" autocomplete="name" />
                        <x-input name="email" label="Email" type="email" :value="$user?->email" required maxlength="255" autocomplete="email" />

                        <div>
                            <label for="field-topic" class="ag-label">What is it about?</label>
                            <select id="field-topic" name="topic" class="ag-input">
                                @foreach (\App\Models\ContactMessage::TOPICS as $key => $label)
                                    <option value="{{ $key }}" @selected($topic === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('topic')<p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="field-product" class="ag-label">Product <span class="font-normal text-ag-muted">(optional)</span></label>
                            <select id="field-product" name="product" class="ag-input">
                                <option value="">Not product specific</option>
                                @foreach (\App\Models\ContactMessage::PRODUCTS as $key => $label)
                                    <option value="{{ $key }}" @selected($productSel === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('product')<p class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p>@enderror
                        </div>

                        <div class="sm:col-span-2">
                            <x-input name="subject" label="Subject" required maxlength="150" placeholder="Console shows no styles on my domain" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="field-message" class="ag-label">Message</label>
                            <textarea id="field-message" name="message" rows="7" required minlength="10" maxlength="5000"
                                placeholder="What happened, what you expected, and any error message. Versions and OS help us a lot."
                                @error('message') aria-invalid="true" aria-describedby="field-message-error" @enderror
                                @class(['ag-input resize-y', 'border-ag-danger' => $errors->has('message')])>{{ old('message') }}</textarea>
                            @error('message')<p id="field-message-error" class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
                            <x-button type="submit">Send message <x-glyph name="arrow-right" /></x-button>
                            <span class="text-xs text-ag-muted">We only use your email to reply to this message.</span>
                        </div>
                    </form>
                </x-card>

                <aside class="space-y-4">
                    <a href="{{ route('known-problems') }}" class="lift block rounded-card bg-white p-5 shadow-ag">
                        <x-glyph name="shield-check" :size="18" class="mb-3 text-ag-teal" />
                        <div class="font-medium text-ag-ink">Quick fixes</div>
                        <div class="mt-1 text-sm text-ag-subtle">Common problems and their solutions, ready right now.</div>
                    </a>
                    <a href="{{ route('docs') }}" class="lift block rounded-card bg-white p-5 shadow-ag">
                        <x-glyph name="book-open" :size="18" class="mb-3 text-ag-teal" />
                        <div class="font-medium text-ag-ink">Documentation</div>
                        <div class="mt-1 text-sm text-ag-subtle">Install, configure and run AtGlance.</div>
                    </a>
                </aside>
            </section>
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
