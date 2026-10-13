<?php

namespace App\Filament\Pages;

use App\Support\ProductShowcase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Editor for the product showcase pages (/console and /cli): hero, feature sections with
 * screenshots, extra feature cards, gallery and which built-in blocks are shown.
 */
class ProductPages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Product pages';

    protected static ?string $title = 'Product pages';

    protected static ?string $slug = 'product-pages';

    protected string $view = 'filament.pages.download-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $state = [];
        foreach (array_keys(ProductShowcase::PRODUCTS) as $p) {
            $state[$p] = ProductShowcase::get($p);
        }
        $this->form->fill($state);
    }

    public function getSubheading(): ?string
    {
        return 'Edit the public Management Console and CLI pages: text, feature sections and screenshots. Changes are live after Save.';
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (ProductShowcase::PRODUCTS as $p => $label) {
            $tabs[] = Tab::make($label)
                ->icon($p === 'cli' ? Heroicon::OutlinedCommandLine : Heroicon::OutlinedServerStack)
                ->schema([
                    Tabs::make("{$p}-sections")->tabs([
                        Tab::make('Hero')->icon(Heroicon::OutlinedSparkles)->schema($this->heroFields($p)),
                        Tab::make('Feature sections')->icon(Heroicon::OutlinedSquares2x2)->schema($this->featureFields($p)),
                        Tab::make('More features')->icon(Heroicon::OutlinedSquaresPlus)->schema($this->extraFields($p)),
                        Tab::make('Gallery')->icon(Heroicon::OutlinedPhoto)->schema($this->galleryFields($p)),
                        Tab::make('Page blocks')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema($this->blockFields($p)),
                    ])->persistTabInQueryString("{$p}-tab"),
                    Actions::make([
                        Action::make("save_{$p}")->label('Save Changes')->action(fn () => $this->save()),
                        Action::make("view_{$p}")
                            ->label('View page')
                            ->color('gray')
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->url(route($p), shouldOpenInNewTab: true),
                        Action::make("reset_{$p}")
                            ->label('Reset to defaults')
                            ->color('danger')
                            ->link()
                            ->requiresConfirmation()
                            ->modalHeading("Reset the {$label} page?")
                            ->modalDescription('All text goes back to the default and uploaded images are removed. This cannot be undone.')
                            ->action(function () use ($p) {
                                ProductShowcase::reset($p);
                                $this->mount();
                                Notification::make()->title('Page reset to defaults')->success()->send();
                            }),
                    ])->extraAttributes(['class' => 'agdl-savebar']),
                ]);
        }

        return $schema->components([
            Tabs::make('products')
                ->vertical()
                ->contained(false)
                ->persistTabInQueryString('product')
                ->extraAttributes(['class' => 'agdl-products'])
                ->tabs($tabs)
                ->columnSpanFull(),
        ])->statePath('data');
    }

    private static function icons(): array
    {
        return array_combine(ProductShowcase::ICONS, ProductShowcase::ICONS);
    }

    private static function image(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory(ProductShowcase::IMAGE_DIR)
            ->visibility('public')
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
            ->maxSize(8192)
            ->imageEditor()
            ->imagePreviewHeight('180')
            ->helperText('PNG, JPG, WebP or GIF up to 8 MB. 1600×1000 px (16:10) looks best.');
    }

    private function heroFields(string $p): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make("{$p}.hero.badge")->label('Badge above the title')->maxLength(80),
                TextInput::make("{$p}.hero.title")->label('Title')->required()->maxLength(160)->columnSpanFull(),
                Textarea::make("{$p}.hero.subtitle")->label('Subtitle')->rows(3)->maxLength(500)->columnSpanFull(),
                TextInput::make("{$p}.hero.primary_label")->label('Main button text')->maxLength(40),
                TextInput::make("{$p}.hero.primary_url")->label('Main button link')->maxLength(255)->placeholder('/dashboard/install/'.$p),
                TextInput::make("{$p}.hero.secondary_label")->label('Second button text')->maxLength(40),
                TextInput::make("{$p}.hero.secondary_url")->label('Second button link')->maxLength(255)->placeholder('#features'),
                Repeater::make("{$p}.hero.ticks")
                    ->label('Check marks under the buttons')
                    ->simple(TextInput::make('text')->maxLength(60)->required())
                    ->reorderable()
                    ->maxItems(6)
                    ->addActionLabel('Add check mark')
                    ->columnSpanFull(),
            ]),
            Fieldset::make('Hero screenshot')->columns(2)->schema([
                self::image("{$p}.hero.image", 'Image')->columnSpanFull(),
                TextInput::make("{$p}.hero.capture_path")
                    ->label($p === 'cli' ? 'Command shown in the window bar' : 'Console page (shown in the window bar)')
                    ->maxLength(120),
                TextInput::make("{$p}.hero.capture_hint")->label('Note: what to capture (admins only)')->maxLength(300),
            ]),
        ];
    }

    private function featureFields(string $p): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make("{$p}.features_heading.eyebrow")->label('Small heading')->maxLength(60),
                TextInput::make("{$p}.features_heading.title")->label('Heading')->maxLength(120),
                Textarea::make("{$p}.features_heading.sub")->label('Text under the heading')->rows(2)->maxLength(300)->columnSpanFull(),
            ]),
            Repeater::make("{$p}.features")
                ->label('Sections (drag to reorder; text and screenshot alternate sides)')
                ->itemLabel(fn (array $state) => trim(($state['visible'] ?? true ? '' : '[hidden] ').($state['eyebrow'] ?? '').' — '.($state['title'] ?? ''), ' —'))
                ->collapsible()
                ->collapsed()
                ->cloneable()
                ->reorderableWithButtons()
                ->addActionLabel('Add feature section')
                ->defaultItems(0)
                ->columns(2)
                ->schema([
                    Select::make('icon')->options(self::icons())->searchable()->required()->default('monitor'),
                    TextInput::make('eyebrow')->label('Small heading')->required()->maxLength(60)->live(onBlur: true),
                    TextInput::make('title')->required()->maxLength(120)->live(onBlur: true)->columnSpanFull(),
                    Textarea::make('text')->rows(3)->maxLength(600)->columnSpanFull(),
                    Repeater::make('bullets')
                        ->label('Bullet points')
                        ->simple(TextInput::make('text')->maxLength(140)->required())
                        ->reorderable()
                        ->maxItems(6)
                        ->addActionLabel('Add bullet')
                        ->columnSpanFull(),
                    self::image('image', 'Screenshot')->columnSpanFull(),
                    TextInput::make('caption')->label('Caption under the screenshot')->maxLength(200)->columnSpanFull(),
                    TextInput::make('capture_path')
                        ->label($p === 'cli' ? 'Command shown in the window bar' : 'Console page (shown in the window bar)')
                        ->maxLength(120),
                    TextInput::make('capture_hint')->label('Note: what to capture (admins only)')->maxLength(300),
                    Toggle::make('visible')->label('Show on the page')->default(true),
                ]),
        ];
    }

    private function extraFields(string $p): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make("{$p}.extras_heading.eyebrow")->label('Small heading')->maxLength(60),
                TextInput::make("{$p}.extras_heading.title")->label('Heading')->maxLength(120),
            ]),
            Repeater::make("{$p}.extras")
                ->label('Feature cards (shown as a grid, no screenshot)')
                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                ->collapsible()
                ->reorderableWithButtons()
                ->addActionLabel('Add card')
                ->defaultItems(0)
                ->grid(2)
                ->schema([
                    Select::make('icon')->options(self::icons())->searchable()->required()->default('check'),
                    TextInput::make('title')->required()->maxLength(80)->live(onBlur: true),
                    Textarea::make('text')->rows(2)->maxLength(240),
                ]),
        ];
    }

    private function galleryFields(string $p): array
    {
        return [
            Repeater::make("{$p}.gallery")
                ->label('Extra screenshots, shown as a gallery near the end of the page')
                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                ->collapsible()
                ->reorderableWithButtons()
                ->addActionLabel('Add screenshot')
                ->defaultItems(0)
                ->grid(2)
                ->schema([
                    self::image('image', 'Screenshot')->required(),
                    TextInput::make('title')->required()->maxLength(100)->live(onBlur: true),
                    TextInput::make('caption')->maxLength(200),
                ]),
        ];
    }

    private function blockFields(string $p): array
    {
        $toggles = [];
        foreach (ProductShowcase::SECTIONS[$p] as $key => $label) {
            $toggles[] = Toggle::make("{$p}.sections.{$key}")->label($label)->default(true);
        }

        return [
            Fieldset::make('Built-in blocks after the features')->columns(2)->schema($toggles),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(ProductShowcase::PRODUCTS) as $p) {
            if (isset($data[$p])) {
                ProductShowcase::save($p, $data[$p]);
            }
        }

        Notification::make()->title('Product pages saved')->success()->send();
    }
}
