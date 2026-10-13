<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Everything on the public /contact page, plus the social links shown in the site footer.
 * Only the general email is required; empty fields are hidden on the site.
 */
class ContactSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Contact';

    protected static ?string $title = 'Contact settings';

    /** Values for keys that older saved settings do not have yet. */
    public const DEFAULTS = [
        'headline' => 'Talk to a real engineer.',
        'intro' => 'AtGlance is built by a small team. If you reach out, an actual SRE will read your message.',
        'show_address' => true,
        'show_social_in_footer' => true,
        'show_form' => true,
        'form_recipient' => 'support@atglance.live',
        'form_title' => 'Send us a message',
        'form_intro' => 'Report a problem, ask a question or send feedback. We reply by email.',
    ];

    /** Social link keys => [label, glyph icon]. */
    public const SOCIALS = [
        'github' => ['GitHub', 'github'],
        'twitter' => ['X / Twitter', 'twitter'],
        'linkedin' => ['LinkedIn', 'linkedin'],
        'youtube' => ['YouTube', 'youtube'],
        'discord' => ['Discord', 'message-circle'],
        'slack' => ['Slack community', 'message-square'],
        'status_page' => ['Status page', 'activity'],
    ];

    protected function settingKey(): string
    {
        return Setting::CONTACT;
    }

    public function mount(): void
    {
        $this->form->fill(Setting::get($this->settingKey()) + self::DEFAULTS);
    }

    protected function fields(): array
    {
        $socials = [];
        foreach (self::SOCIALS as $key => [$label]) {
            $socials[] = TextInput::make($key)->label("{$label} URL")->url()->maxLength(255)
                ->placeholder($key === 'status_page' ? 'https://status.atglance.live' : null);
        }

        return [
            Section::make('Contact page')
                ->description('Heading and text at the top of /contact.')
                ->icon(Heroicon::OutlinedDocumentText)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('headline')->required()->maxLength(120),
                    TextInput::make('response_time')
                        ->label('Response time')
                        ->placeholder('We reply within 1 business day')
                        ->maxLength(120),
                    Textarea::make('intro')->label('Intro text')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),

            Section::make('Contact form')
                ->description('The form on /contact. Messages are saved under Support → Messages and emailed to the address below.')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('show_form')->label('Show the form on /contact')->columnSpanFull(),
                    TextInput::make('form_recipient')
                        ->label('Form messages go to')
                        ->email()
                        ->required()
                        ->helperText('Every new message is emailed here. Reply-To is the sender, so you can answer directly.'),
                    TextInput::make('form_title')->label('Form heading')->maxLength(80),
                    Textarea::make('form_intro')->label('Text above the form')->rows(2)->maxLength(300)->columnSpanFull(),
                ]),

            Section::make('Email addresses')
                ->description('Shown as cards on /contact. Leave a field empty to hide it.')
                ->icon(Heroicon::OutlinedEnvelope)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('email')->label('General')->email()->required(),
                    TextInput::make('sales_email')->label('Sales')->email(),
                    TextInput::make('support_email')->label('Support')->email(),
                    TextInput::make('security_email')
                        ->label('Security reports')
                        ->email()
                        ->helperText('For responsible disclosure of vulnerabilities.'),
                    TextInput::make('billing_email')->label('Billing')->email(),
                    TextInput::make('press_email')->label('Press / partnerships')->email(),
                ]),

            Section::make('Phone and hours')
                ->icon(Heroicon::OutlinedPhone)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('phone')->tel()->placeholder('+91 98765 43210')->maxLength(40),
                    TextInput::make('support_hours')
                        ->label('Support hours')
                        ->placeholder('Mon–Fri, 09:00–18:00 IST')
                        ->maxLength(120),
                ]),

            Section::make('Office')
                ->icon(Heroicon::OutlinedMapPin)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('company_name')->label('Company name')->maxLength(120),
                    TextInput::make('map_url')->label('Map link')->url()->placeholder('https://maps.google.com/...'),
                    Textarea::make('address')->rows(3)->maxLength(500)->columnSpanFull(),
                    Toggle::make('show_address')->label('Show the address on /contact'),
                ]),

            Section::make('Social links')
                ->description('Shown on /contact and, if enabled, in the site footer. Leave empty to hide.')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ...$socials,
                    Toggle::make('show_social_in_footer')->label('Show social icons in the site footer')->columnSpanFull(),
                ]),
        ];
    }
}
