<?php

namespace App\Filament\Pages;

use App\Models\BlogApiKey;
use App\Models\Setting;
use App\Support\Blog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/** Admin → Blog → Blog settings: turn the blog on or off and set its page. */
class BlogSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Blog settings';

    protected static ?string $title = 'Blog settings';

    protected static ?string $slug = 'blog-settings';

    protected string $view = 'filament.pages.blog-settings';

    /** The key just created: shown once on this page, never stored in plain text. */
    public ?string $newKey = null;

    protected function settingKey(): string
    {
        return Setting::BLOG;
    }

    public function mount(): void
    {
        $this->form->fill(array_replace(Blog::DEFAULTS, Setting::get(Setting::BLOG)));
    }

    /** Keys for the Blog API (/api/blog), newest first. */
    public function getApiKeys()
    {
        return BlogApiKey::with('creator:id,name')->latest()->get();
    }

    public function createKeyAction(): Action
    {
        return Action::make('createKey')
            ->label('Create API key')
            ->icon(Heroicon::OutlinedKey)
            ->modalHeading('New Blog API key')
            ->modalDescription('The key can create, change, publish and delete blog posts, and nothing else. It is shown once.')
            ->schema([
                TextInput::make('name')->label('Name')->placeholder('n8n blog workflow')->required()->maxLength(100),
            ])
            ->action(function (array $data) {
                [, $plain] = BlogApiKey::issue($data['name'], auth()->user());
                $this->newKey = $plain;
                Notification::make()->title('API key created')->body('Copy it now: it is not shown again.')->success()->send();
            });
    }

    public function revokeKeyAction(): Action
    {
        return Action::make('revokeKey')
            ->label('Revoke')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Revoke "'.(BlogApiKey::find($arguments['id'])?->name ?? 'key').'"?')
            ->modalDescription('Workflows that use this key stop working at once. Posts they created stay.')
            ->action(function (array $arguments) {
                BlogApiKey::whereKey($arguments['id'])->delete();
                $this->newKey = null;
                Notification::make()->title('API key revoked')->success()->send();
            });
    }

    protected function fields(): array
    {
        return [
            Section::make('Blog')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('enabled')->label('Blog is public')->columnSpanFull()
                        ->helperText('Off: /blog and every post answer "not found". Posts are kept.'),
                    Toggle::make('show_in_nav')->label('Show "Blog" in the top navigation')
                        ->helperText('Only when at least one post is published.'),
                    Toggle::make('rss')->label('RSS feed at /blog/rss.xml'),
                    TextInput::make('title')->label('Page title')->required()->maxLength(60),
                    TextInput::make('per_page')->label('Posts per page')->numeric()->minValue(3)->maxValue(48)->required(),
                    Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300)->columnSpanFull()
                        ->helperText('Under the title on /blog, and the page description for search engines.'),
                ]),
        ];
    }
}
