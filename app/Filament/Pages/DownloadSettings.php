<?php

namespace App\Filament\Pages;

use App\Models\DownloadFile;
use App\Models\Setting;
use App\Support\Downloads;
use App\Support\ReleaseFiles;
use App\Support\ReleaseNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
use Illuminate\Support\Facades\Storage;
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

    /** What each OS upload box accepts, per product: [title, help text, example names]. */
    private const PACKAGE_HELP = [
        'cli' => [
            'linux' => ['Linux packages', 'Debian / Ubuntu .deb (amd64, arm64, armhf), RHEL / Fedora .rpm (x86_64, aarch64) and .tar.gz archives for any systemd Linux.', 'atglance_2.3.0_amd64.deb · atglance-2.3.0-1.x86_64.rpm · atglance_2.3.0_linux_arm64.tar.gz'],
            'windows' => ['Windows packages', 'The .msi installer, the .zip (atglance.exe with install.ps1) or the plain .exe. For Windows Server; the agent runs as the AtGlanceAgent service.', 'atglance_3.0.0_windows_amd64.msi · atglance_3.0.0_windows_amd64.zip'],
            'macos' => ['macOS packages', 'A .pkg installer, a .tar.gz, or the plain binary for Apple silicon (arm64) or Intel (amd64). The agent runs as a launchd job.', 'atglance_3.0.0_darwin_arm64 · atglance_3.0.0_darwin_amd64.tar.gz'],
        ],
        'console' => [
            'linux' => ['Linux scripts', 'The installer and the update script for a Linux server with Docker.', 'atglance-installer.sh · atglance_update.sh'],
            'windows' => ['Windows scripts', 'The PowerShell installer and update script for Docker Desktop or Windows Server.', 'atglance-installer.ps1 · atglance_update.ps1'],
        ],
    ];

    private const OS_ICONS = ['linux' => 'OutlinedCommandLine', 'windows' => 'OutlinedWindow', 'macos' => 'OutlinedComputerDesktop'];

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
            foreach (array_keys(Downloads::platformsFor($p)) as $os) {
                $state[$p]["new_files_{$os}"] = [];
                $state[$p]["new_file_names_{$os}"] = [];
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->fields())->statePath('data');
    }

    private static function osIcon(string $os): Heroicon
    {
        return constant(Heroicon::class.'::'.(self::OS_ICONS[$os] ?? 'OutlinedCommandLine'));
    }

    protected function fields(): array
    {
        $all = Downloads::all();
        $sections = [];

        foreach (Downloads::PRODUCTS as $p => $label) {
            $latest = $all[$p]['releases'][0]['version'] ?? null;
            $withChecksum = Downloads::hasChecksum($p);
            $cli = $p === 'cli';
            $osNames = implode(', ', Downloads::platformsFor($p));

            $packageBoxes = [];
            foreach (Downloads::platformsFor($p) as $os => $osLabel) {
                [$title, $help, $example] = self::PACKAGE_HELP[$p][$os];
                $packageBoxes[] = Section::make($title)
                    ->icon(self::osIcon($os))
                    ->description($help)
                    ->compact()
                    ->columnSpan(1)
                    ->schema([
                        self::filesField("{$p}.new_files_{$os}", "{$p}.new_file_names_{$os}")
                            ->hiddenLabel()
                            ->helperText('e.g. '.$example),
                    ]);
            }

            $sections[] = Tab::make($label)
                ->icon($cli ? Heroicon::OutlinedCommandLine : Heroicon::OutlinedServerStack)
                ->badge($latest ? "v{$latest}" : null)
                ->schema([
                    // What is published now, per OS, and where it shows.
                    View::make('filament.downloads.overview')
                        ->viewData(['productKey' => $p, 'product' => $all[$p], 'files' => ReleaseFiles::latest($p), 'platforms' => Downloads::platformsFor($p)])
                        ->columnSpanFull(),

                    Section::make('1. New release')
                        ->icon(Heroicon::OutlinedRocketLaunch)
                        ->description('Enter a version to publish it. Leave the version empty to save only the instructions in step 4.')
                        ->columns(2)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make("{$p}.new_version")
                                ->label('Version')
                                ->placeholder($cli ? '2.3.0' : '2.4.0')
                                ->maxLength(30)
                                ->regex('/^[A-Za-z0-9._+-]+$/')
                                ->helperText('Numbers and dots, for example 2.3.0. The same version again replaces that release.'),
                            TextInput::make("{$p}.new_checksum")
                                ->visible($withChecksum)
                                ->label('SHA-256 of the main Linux package (optional)')
                                ->placeholder('Filled in from the uploaded .deb')
                                ->regex('/^[A-Fa-f0-9]{64}$/')
                                ->validationMessages(['regex' => 'Enter a SHA-256 checksum: 64 hex characters.'])
                                ->helperText('Only needed when you upload no files. Every uploaded file gets its own checksum.')
                                ->dehydrateStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : null),
                            ...self::releaseDetailFields("{$p}.new_"),
                            Toggle::make("{$p}.notify")
                                ->label('Email all verified users about this release')
                                ->helperText('The email shows the summary (or the notes) and links to the release notes page.')
                                ->columnSpanFull(),
                        ]),

                    Section::make($cli ? '2. Packages for '.$osNames : '2. Scripts for '.$osNames)
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->description($cli
                            ? 'Upload each operating system in its own box. The architecture and SHA-256 are read from every file. Files go with the version in step 1; to add files to an older release, use "Add files" in step 3.'
                            : 'Upload the installer and update scripts. The newest upload of a name is what "curl ... | bash" and "irm ... | iex" download.')
                        ->columns(['default' => 1, 'lg' => count($packageBoxes)])
                        ->columnSpanFull()
                        ->schema($packageBoxes),

                    Section::make('3. Release history')
                        ->icon(Heroicon::OutlinedClock)
                        ->description('Latest first. Each release lists its files by operating system.')
                        ->columnSpanFull()
                        ->schema([
                            View::make('filament.downloads.history')
                                ->viewData([
                                    'releases' => $all[$p]['releases'],
                                    'product' => $all[$p],
                                    'max' => Downloads::MAX_RELEASES,
                                    'withChecksum' => $withChecksum,
                                    'productKey' => $p,
                                    'files' => ReleaseFiles::byVersion($p),
                                    'platforms' => Downloads::platformsFor($p),
                                ]),
                        ]),

                    Section::make('4. Install and update instructions')
                        ->icon(Heroicon::OutlinedBookOpen)
                        ->description('Shown step by step in the dashboard installer. The download link and file name come from the latest release\'s files; fill them in only for a file hosted somewhere else.')
                        ->columnSpanFull()
                        ->schema([$this->platformTabs($p)]),

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
            $example = match (true) {
                $p === 'console' => $win ? 'atglance-installer.ps1' : 'atglance-installer.sh',
                $os === 'windows' => 'atglance_3.0.0_windows_amd64.msi',
                $os === 'macos' => 'atglance_3.0.0_darwin_arm64',
                default => 'atglance_2.3.0_amd64.deb',
            };
            $tabs[] = Tab::make($label)
                ->icon(self::osIcon($os))
                ->schema([
                    Fieldset::make('Install')
                        ->columns(2)
                        ->schema([
                            TextInput::make("{$p}.platforms.{$os}.url")
                                ->label('Download URL (optional)')
                                ->url()
                                ->placeholder('From the uploaded files')
                                ->helperText('Only for a file hosted elsewhere.'),
                            TextInput::make("{$p}.platforms.{$os}.file_name")
                                ->label('File name (optional)')
                                ->placeholder($example)
                                ->helperText($p === 'cli' ? 'Used in the checksum command when the URL is set here.' : null),
                            MarkdownEditor::make("{$p}.platforms.{$os}.install_steps")
                                ->label("Install steps on {$label}")
                                ->helperText("For a new installation. Use ```{$lang} code blocks for commands.")
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Update')
                        ->columns(2)
                        ->schema([
                            TextInput::make("{$p}.platforms.{$os}.script_url")
                                ->label('Update script URL (optional)')
                                ->url()
                                ->placeholder($p === 'console' ? 'From the uploaded update script' : 'https://.../update.sh')
                                ->helperText('Download link for an update script.')
                                ->columnSpanFull(),
                            MarkdownEditor::make("{$p}.platforms.{$os}.update_steps")
                                ->label("Update steps on {$label}")
                                ->helperText("How to update an existing {$what} to the latest version.")
                                ->columnSpanFull(),
                        ]),
                ]);
        }

        return Tabs::make("{$p}-platforms")->tabs($tabs)->columnSpanFull();
    }

    /** Several release files, kept on the local disk until attachUploads() moves them to the downloads disk. */
    private static function filesField(string $name, string $namesField): FileUpload
    {
        return FileUpload::make($name)
            ->multiple()
            ->disk('local')
            ->directory('download-uploads')
            ->storeFileNamesIn($namesField)
            ->maxSize((int) config('atglance.download_upload_max_kb', 204800))
            ->maxParallelUploads(2);
    }

    /** Files waiting in any of the per-OS upload boxes of one product. */
    private static function hasUploads(array $productData, string $p): bool
    {
        foreach (array_keys(Downloads::platformsFor($p)) as $os) {
            if (! empty($productData["new_files_{$os}"])) {
                return true;
            }
        }

        return false;
    }

    /** Move uploaded files from the local disk into a release. Returns how many were added. */
    private static function attachUploads(string $product, string $version, array $paths, array $names, array $options = []): int
    {
        $n = 0;
        foreach ($paths as $key => $path) {
            if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
                continue;
            }
            $name = $names[$path] ?? $names[$key] ?? basename($path);
            if (! ReleaseFiles::allowed($name)) {
                Storage::disk('local')->delete($path);
                Notification::make()->title("Skipped {$name}")->body('Allowed: .deb, .rpm, .tar.gz, .zip, .msi, .exe, .pkg, .dmg, .sh, .ps1 and .txt. SHA256SUMS is built for you.')->warning()->send();

                continue;
            }
            ReleaseFiles::store($product, $version, Storage::disk('local')->path($path), $name, $options);
            Storage::disk('local')->delete($path);
            $n++;
        }

        return $n;
    }

    /** "Add files" button on a release in the history. Arguments: product, index. */
    public function uploadFilesAction(): Action
    {
        return Action::make('uploadFiles')
            ->label('Add files')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->size('sm')
            ->modalHeading(function (array $arguments) {
                $v = Downloads::all()[$arguments['product']]['releases'][$arguments['index']]['version'] ?? '';

                return 'Add files to '.Downloads::PRODUCTS[$arguments['product']].' v'.$v;
            })
            ->modalDescription('A file with the same name replaces the old one. Platform and architecture are read from the file name unless you choose a platform.')
            ->schema(fn (array $arguments) => [
                self::filesField('files', 'file_names')->label('Files')->required(),
                Select::make('platform')
                    ->label('Operating system')
                    ->options(['auto' => 'From the file name'] + Downloads::platformsFor($arguments['product'] ?? 'cli'))
                    ->default('auto')
                    ->selectablePlaceholder(false),
            ])
            ->action(function (array $data, array $arguments) {
                $p = $arguments['product'];
                $release = Downloads::all()[$p]['releases'][$arguments['index']] ?? null;
                if (! $release) {
                    Notification::make()->title('Release not found')->danger()->send();

                    return;
                }
                $platform = $data['platform'] ?? 'auto';
                $n = self::attachUploads($p, $release['version'], (array) ($data['files'] ?? []), (array) ($data['file_names'] ?? []),
                    isset(DownloadFile::PLATFORMS[$platform]) ? ['platform' => $platform] : []);
                Notification::make()->title("{$n} file(s) added to v{$release['version']}")->success()->send();
            });
    }

    /** Trash button next to one file of a release. Arguments: id. */
    public function deleteFileAction(): Action
    {
        return Action::make('deleteFile')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Remove '.(DownloadFile::find($arguments['id'])?->file_name ?? 'file').'?')
            ->modalDescription('The file is deleted. Its download links stop working, including the old app.atglance.live link.')
            ->action(function (array $arguments) {
                if ($file = DownloadFile::find($arguments['id'])) {
                    ReleaseFiles::delete($file);
                }
                Notification::make()->title('File removed')->success()->send();
            });
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
            if (Downloads::hasChecksum($p) && filled($data[$p]['new_version'] ?? null) && blank($data[$p]['new_checksum'] ?? null) && ! self::hasUploads($data[$p], $p)) {
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
                $version = trim($data[$p]['new_version']);
                $added = 0;
                foreach (array_keys(Downloads::platformsFor($p)) as $os) {
                    $added += self::attachUploads($p, $version, (array) ($data[$p]["new_files_{$os}"] ?? []), (array) ($data[$p]["new_file_names_{$os}"] ?? []), ['platform' => $os]);
                }
                $checksum = $data[$p]['new_checksum'] ?? null;
                if (blank($checksum) && $added) {
                    $checksum = ReleaseFiles::primary(ReleaseFiles::forRelease($p, $version), 'linux')?->sha256;
                }
                $product = Downloads::publish($p, $version, $checksum, $data[$p]['new_notes'] ?? null, [
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
            $files = ReleaseFiles::forRelease($p, $info['release']['version'])->count();
            $line = Downloads::PRODUCTS[$p].' '.$info['release']['version'].' published'.($files ? " with {$files} file(s)" : '');
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
