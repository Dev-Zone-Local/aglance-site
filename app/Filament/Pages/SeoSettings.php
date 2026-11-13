<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Seo;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/** Admin → SEO: how the public site appears in search results and when shared. */
class SeoSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Search & sharing';

    protected static ?string $title = 'SEO settings';

    protected static ?string $slug = 'seo';

    protected function settingKey(): string
    {
        return Setting::SEO;
    }

    public function mount(): void
    {
        $this->form->fill(array_replace(Seo::DEFAULTS, Setting::get(Setting::SEO)));
    }

    protected function fields(): array
    {
        return [
            Section::make('Titles and descriptions')
                ->description('Used on every public page that does not set its own. Search engines show about 60 characters of a title and 155 of a description.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('site_name')->label('Site name')->required()->maxLength(60)
                        ->helperText('Added after each page title, e.g. "Pricing · AtGlance".'),
                    TextInput::make('title_separator')->label('Separator')->maxLength(5)->placeholder(' · ')
                        ->helperText('Between the page title and the site name.')
                        ->dehydrateStateUsing(fn (?string $state) => $state === null || $state === '' ? ' · ' : $state),
                    TextInput::make('home_title')->label('Home page title')->required()->maxLength(70)->columnSpanFull(),
                    Textarea::make('default_description')->label('Default description')->required()->rows(2)->maxLength(170)->columnSpanFull(),
                ]),

            Section::make('Sharing')
                ->description('What links look like on LinkedIn, X, Slack and other apps.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('og_image')->label('Sharing image')->image()->disk('public')->directory(Seo::IMAGE_DIR)
                        ->visibility('public')->maxSize(4096)->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                        ->helperText('1200×630 px, PNG, JPG or WebP. Empty = the AtGlance logo.'),
                    TextInput::make('twitter_handle')->label('X (Twitter) account')->prefix('@')->maxLength(30)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? ltrim(trim($state), '@') : null),
                ]),

            Section::make('Search engines')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('allow_indexing')->label('Let search engines index the site')->columnSpanFull()
                        ->helperText('Turn off on a staging copy: every page gets "noindex" and robots.txt disallows everything.'),
                    Textarea::make('robots_disallow')->label('More paths to keep out of search')->rows(3)->columnSpanFull()
                        ->placeholder("/known-problems\n/features/old-page")
                        ->helperText('One path per line, added to robots.txt. /admin, /api/, /dashboard, /reset-password and /auth/ are always blocked.'),
                    TextInput::make('google_verification')->label('Google Search Console code')->maxLength(100)
                        ->helperText('Only the content="..." value of the meta tag.'),
                    TextInput::make('bing_verification')->label('Bing Webmaster code')->maxLength(100),
                ]),

            Section::make('Per-page overrides')
                ->description('Change the title, description or sharing image of one page, or keep it out of search.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('pages')
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->addActionLabel('Add page')
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['path'] ?? null)
                        ->columns(2)
                        ->schema([
                            TextInput::make('path')->label('Path')->required()->placeholder('/pricing')->maxLength(200)
                                ->dehydrateStateUsing(fn (?string $state) => '/'.trim((string) $state, '/ ')),
                            Toggle::make('noindex')->label('Do not index this page')->inline(false),
                            TextInput::make('title')->label('Title')->maxLength(70)->helperText('The whole title, site name included.'),
                            FileUpload::make('image')->label('Sharing image')->image()->disk('public')->directory(Seo::IMAGE_DIR)->visibility('public')->maxSize(4096),
                            Textarea::make('description')->label('Description')->rows(2)->maxLength(170)->columnSpanFull(),
                        ]),
                ]),
        ];
    }
}
