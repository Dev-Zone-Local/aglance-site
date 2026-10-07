<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Downloads;
use App\Support\ReleaseNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * CLI and Management Console downloads: URL, install steps, and a release history
 * (latest first, up to 20 versions with SHA-256 checksums, title, type, date and Markdown
 * release notes). The notes are public on /releases. Publishing a release can email all
 * verified users.
 */
class DownloadSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?string $title = 'Download settings';

    protected string $view = 'filament.pages.download-settings';

    protected function settingKey(): string
    {
        return Setting::DOWNLOADS;
    }

    public function mount(): void
    {
        $state = [];
        foreach (Downloads::all() as $p => $product) {
            $state[$p] = [
                'url' => $product['url'],
                'file_name' => $product['file_name'],
                'install_steps' => $product['install_steps'],
                'platforms' => $product['platforms'] ?? null,
                'new_version' => null,
                'new_checksum' => null,
                'new_title' => null,
                'new_type' => null,
                'new_released_at' => now()->toDateString(),
                'new_summary' => null,
                'new_notes' => Downloads::NOTES_TEMPLATE,
                'notify' => false,
            ];
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->fields())->statePath('data');
    }

    protected function fields(): array
    {
        $all = Downloads::all();
        $sections = [];

        foreach (Downloads::PRODUCTS as $p => $label) {
            $latest = $all[$p]['releases'][0]['version'] ?? null;
            $withChecksum = Downloads::hasChecksum($p);

            $sections[] = Tab::make($label)
                ->icon($p === 'cli' ? Heroicon::OutlinedCommandLine : Heroicon::OutlinedServerStack)
                ->badge($latest ? "v{$latest}" : null)
                ->schema([Section::make($label)
                    ->description($latest ? "Latest version: {$latest}" : 'No release published yet')
                    ->icon($p === 'cli' ? Heroicon::OutlinedCommandLine : Heroicon::OutlinedServerStack)
                    ->columns(2)
                    ->schema([
                        Fieldset::make('Publish a new release')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make("{$p}.new_version")
                                    ->label('Version')
                                    ->placeholder('2.1.0')
                                    ->maxLength(30)
                                    ->regex('/^[A-Za-z0-9._+-]+$/'),
                                TextInput::make("{$p}.new_checksum")
                                    ->visible($withChecksum)
                                    ->label('SHA-256 checksum')
                                    ->placeholder('64 hex characters')
                                    ->regex('/^[A-Fa-f0-9]{64}$/')
                                    ->validationMessages(['regex' => 'Enter a SHA-256 checksum: 64 hex characters.'])
                                    ->helperText('Required when you enter a version.')
                                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : null),
                                ...self::releaseDetailFields("{$p}.new_"),
                                Toggle::make("{$p}.notify")
                                    ->label('Email all verified users about this release')
                                    ->helperText('The email shows the summary (or the notes) and links to the release notes page.')
                                    ->columnSpanFull(),
                            ]),

                        View::make('filament.downloads.history')
                            ->viewData([
                                'releases' => $all[$p]['releases'],
                                'product' => $all[$p],
                                'max' => Downloads::MAX_RELEASES,
                                'withChecksum' => $withChecksum,
                                'productKey' => $p,
                            ])
                            ->columnSpanFull(),

                        $this->platformTabs($p),
                    ]),
                    // Save bar at the end of each product, like the console settings tabs.
                    Actions::make([
                        Action::make("save_{$p}")
                            ->label('Save Changes')
                            ->action(fn () => $this->save()),
                    ])->extraAttributes(['class' => 'agdl-savebar']),
                ]);
        }

        // Sidebar: one product visible at a time.
        return [
            Tabs::make('products')
                ->vertical()
                ->contained(false)
                ->persistTabInQueryString('product')
                ->extraAttributes(['class' => 'agdl-products'])
                ->tabs($sections)
                ->columnSpanFull(),
        ];
    }

    /** One tab per platform, each with a separate Install and Update box. */
    private function platformTabs(string $p): Tabs
    {
        $what = $p === 'cli' ? 'CLI' : 'console';
        $tabs = [];
        foreach (Downloads::platformsFor($p) as $os => $label) {
            $win = $os === 'windows';
            $lang = $win ? 'powershell' : 'bash';
            $tabs[] = Tab::make($label)
                ->icon($win ? Heroicon::OutlinedWindow : Heroicon::OutlinedCommandLine)
                ->schema([
                    Fieldset::make('Install')
                        ->columns(2)
                        ->schema([
                            TextInput::make("{$p}.platforms.{$os}.url")
                                ->label('Download URL')
                                ->url()
                                ->placeholder($p === 'cli' ? 'https://.../atglance_2.0.0_all.deb' : ($win ? 'https://.../install-console.ps1' : 'https://.../install.sh')),
                            TextInput::make("{$p}.platforms.{$os}.file_name")
                                ->label('File name')
                                ->placeholder($p === 'cli' ? 'atglance_2.0.0_all.deb' : ($win ? 'install-console.ps1' : 'install.sh'))
                                ->helperText($p === 'cli' ? 'Also used in the checksum command.' : null),
                            MarkdownEditor::make("{$p}.platforms.{$os}.install_steps")
                                ->label('Install steps')
                                ->helperText("For a new installation. Use ```{$lang} code blocks for commands.")
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Update')
                        ->columns(2)
                        ->schema([
                            TextInput::make("{$p}.platforms.{$os}.script_url")
                                ->label('Update script URL')
                                ->url()
                                ->placeholder($win ? 'https://.../update-console.ps1' : "https://.../update-{$p}.sh")
                                ->helperText('Download link for the update script.')
                                ->columnSpanFull(),
                            MarkdownEditor::make("{$p}.platforms.{$os}.update_steps")
                                ->label('Update steps')
                                ->helperText("How to update an existing {$what} to the latest version.")
                                ->columnSpanFull(),
                        ]),
                ]);
        }

        return Tabs::make("{$p}-platforms")->tabs($tabs)->columnSpanFull();
    }

    /**
     * Title, type, date, summary and Markdown notes of a release. Used by the publish form
     * ($prefix "cli.new_") and the edit modal ($prefix "").
     */
    private static function releaseDetailFields(string $prefix): array
    {
        return [
            TextInput::make("{$prefix}title")
                ->label('Title (optional)')
                ->placeholder('Faster discovery and Windows support')
                ->maxLength(120),
            Select::make("{$prefix}type")
                ->label('Release type')
                ->options(Downloads::RELEASE_TYPES)
                ->placeholder('Not set'),
            DatePicker::make("{$prefix}released_at")
                ->label('Release date')
                ->native(false)
                ->displayFormat('d M Y'),
            Textarea::make("{$prefix}summary")
                ->label('Summary')
                ->helperText('One or two sentences. Shown on cards and in the release email.')
                ->rows(2)
                ->maxLength(300)
                ->columnSpanFull(),
            MarkdownEditor::make("{$prefix}notes")
                ->label('Release notes')
                ->helperText('What is in this release. Markdown: headings (###), lists, links, `code` and ```bash blocks. Shown on the public Release notes page.')
                ->maxLength(20000)
                ->columnSpanFull(),
        ];
    }

    /** "Edit" button on a release in the history. Arguments: product, index. */
    public function editReleaseAction(): Action
    {
        return Action::make('editRelease')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments) => 'Edit '.Downloads::PRODUCTS[$arguments['product']].' release')
            ->modalWidth('4xl')
            ->fillForm(function (array $arguments) {
                $r = Downloads::all()[$arguments['product']]['releases'][$arguments['index']] ?? [];

                return [
                    'version' => $r['version'] ?? null,
                    'checksum' => $r['checksum'] ?? null,
                    'title' => $r['title'] ?? null,
                    'type' => $r['type'] ?? null,
                    'released_at' => ! empty($r['released_at']) ? substr($r['released_at'], 0, 10) : null,
                    'summary' => $r['summary'] ?? null,
                    'notes' => $r['notes'] ?? null,
                ];
            })
            ->schema(fn (array $arguments) => [
                TextInput::make('version')->required()->maxLength(30)->regex('/^[A-Za-z0-9._+-]+$/'),
                TextInput::make('checksum')
                    ->label('SHA-256 checksum')
                    ->visible(Downloads::hasChecksum($arguments['product']))
                    ->required()
                    ->regex('/^[A-Fa-f0-9]{64}$/')
                    ->validationMessages(['regex' => 'Enter a SHA-256 checksum: 64 hex characters.']),
                ...self::releaseDetailFields(''),
            ])
            ->action(function (array $data, array $arguments) {
                try {
                    Downloads::updateRelease($arguments['product'], (int) $arguments['index'], trim($data['version']), $data['checksum'] ?? null, $data['notes'] ?? null, $data);
                } catch (HttpException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }
                Notification::make()->title('Release updated')->success()->send();
            });
    }

    /** "Delete" button on a release in the history. Arguments: product, index. */
    public function deleteReleaseAction(): Action
    {
        return Action::make('deleteRelease')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(function (array $arguments) {
                $v = Downloads::all()[$arguments['product']]['releases'][$arguments['index']]['version'] ?? '';

                return "Delete release v{$v}?";
            })
            ->modalDescription('It is removed from the history and the user dashboard. If it is the latest, the next one becomes latest.')
            ->action(function (array $arguments) {
                Downloads::deleteRelease($arguments['product'], (int) $arguments['index']);
                Notification::make()->title('Release deleted')->success()->send();
            });
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $missing = [];
        foreach (array_keys(Downloads::PRODUCTS) as $p) {
            if (Downloads::hasChecksum($p) && filled($data[$p]['new_version'] ?? null) && blank($data[$p]['new_checksum'] ?? null)) {
                $missing["data.{$p}.new_checksum"] = 'Enter the SHA-256 checksum for this release.';
            }
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        $all = Downloads::all();
        $published = [];

        foreach (array_keys(Downloads::PRODUCTS) as $p) {
            foreach (array_keys(Downloads::platformsFor($p)) as $os) {
                $in = $data[$p]['platforms'][$os] ?? [];
                $all[$p]['platforms'][$os] = [
                    'url' => ($in['url'] ?? null) ?: null,
                    'file_name' => ($in['file_name'] ?? null) ?: null,
                    'install_steps' => ($in['install_steps'] ?? null) ?: null,
                    'update_steps' => ($in['update_steps'] ?? null) ?: null,
                    'script_url' => ($in['script_url'] ?? null) ?: null,
                ];
            }
            // Product-level values = Linux (legacy keys, CLI checksum command file name).
            $all[$p]['url'] = $all[$p]['platforms']['linux']['url'];
            $all[$p]['file_name'] = $all[$p]['platforms']['linux']['file_name'];
            $all[$p]['install_steps'] = $all[$p]['platforms']['linux']['install_steps'];
        }
        Downloads::save($all);

        foreach (array_keys(Downloads::PRODUCTS) as $p) {
            if (filled($data[$p]['new_version'] ?? null)) {
                $product = Downloads::publish($p, trim($data[$p]['new_version']), $data[$p]['new_checksum'] ?? null, $data[$p]['new_notes'] ?? null, [
                    'title' => $data[$p]['new_title'] ?? null,
                    'type' => $data[$p]['new_type'] ?? null,
                    'summary' => $data[$p]['new_summary'] ?? null,
                    'released_at' => $data[$p]['new_released_at'] ?? null,
                ]);
                $published[$p] = ['release' => $product['releases'][0], 'notify' => (bool) ($data[$p]['notify'] ?? false)];
            }
        }

        $messages = [];
        foreach ($published as $p => $info) {
            $line = Downloads::PRODUCTS[$p].' '.$info['release']['version'].' published';
            if ($info['notify']) {
                $result = ReleaseNotifier::send($p, $info['release']);
                $line .= ", emailed {$result['sent']} user(s)".($result['failed'] ? " ({$result['failed']} failed)" : '');
            }
            $messages[] = $line.'.';
        }

        // Reset the "publish" fields and reload the history.
        $this->mount();

        Notification::make()
            ->title('Download settings saved')
            ->body($messages ? implode(' ', $messages) : null)
            ->success()
            ->send();
    }
}
