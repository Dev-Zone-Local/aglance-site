<?php

namespace App\Filament\Pages;

use App\Models\FeatureCatalog;
use App\Models\LicensePolicy;
use App\Models\PolicyAuditLog;
use App\Models\PricingPlan;
use App\Support\LicensePolicy\PolicyAudit;
use App\Support\LicensePolicy\PolicyRules;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin → License Policy: the global default and one default per plan for new licence files.
 * Saving changes only licences generated afterwards; issued files never change.
 */
class LicensePolicyPage extends Page
{
    protected string $view = 'filament.pages.license-policy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'License Policy';

    protected static ?string $title = 'License Policy';

    protected static ?string $slug = 'license-policy';

    public ?array $data = [];

    /** Server-side: only admins, also for a direct URL. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /**
     * The global default, then one policy per plan of Admin → Pricing plans (in their order).
     * A plan added there gets a policy that uses the global default until it is changed here.
     *
     * @return Collection<int, LicensePolicy>
     */
    public static function policies(): Collection
    {
        LicensePolicy::global();
        $codes = PricingPlan::query()->orderBy('order')->get()->map(fn (PricingPlan $p) => $p->code())->filter()->unique()->values()->all();
        foreach ($codes as $code) {
            LicensePolicy::query()->firstOrCreate(['scope' => 'plan:'.$code], [
                'inherit_global' => true, 'features' => FeatureCatalog::defaultKeys(),
            ]);
        }
        $order = array_merge([LicensePolicy::GLOBAL], array_map(fn ($c) => 'plan:'.$c, $codes));

        return LicensePolicy::query()->whereIn('scope', $order)->get()->sortBy(fn ($p) => array_search($p->scope, $order))->values();
    }

    public static function formKey(string $scope): string
    {
        return str_replace(':', '_', $scope);
    }

    public function mount(): void
    {
        $state = [];
        foreach (self::policies() as $policy) {
            $state[self::formKey($policy->scope)] = $policy->only([
                'inherit_global', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'max_licences', 'features',
            ]);
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (self::policies() as $policy) {
            $key = self::formKey($policy->scope);
            $isPlan = $policy->scope !== LicensePolicy::GLOBAL;
            $tabs[] = Tab::make($policy->label())->schema(array_filter([
                $isPlan ? Toggle::make("{$key}.inherit_global")
                    ->label('Use the global default for this plan')
                    ->live() : null,
                Grid::make(2)
                    ->visible(fn (Get $get) => ! $isPlan || ! $get("{$key}.inherit_global"))
                    ->schema(self::policyFields($key)),
            ]));
        }

        return $schema->components([Tabs::make('Policies')->tabs($tabs)->columnSpanFull()])->statePath('data');
    }

    /** @return array<int, mixed> */
    public static function policyFields(string $key = ''): array
    {
        $p = $key === '' ? '' : $key.'.';

        return [
            TextInput::make("{$p}max_licences")->label('Licence limit')->integer()
                ->minValue(1)->maxValue(PolicyRules::MAX_LICENCES)->required()
                ->helperText('Total licences a user may hold (keys and their files), at least 1. To get more, users contact AtGlance Support.'),
            TextInput::make("{$p}max_nodes")->label('Node limit')->integer()->minValue(0)->required()
                ->helperText('0 = unlimited. Active nodes (registered systems) a console may have.'),
            TextInput::make("{$p}max_users")->label('User limit')->integer()->minValue(0)->required()
                ->helperText('0 = unlimited. User accounts of all roles in the console.'),
            TextInput::make("{$p}validity_value")->label('License valid up to')->integer()->minValue(1)->maxValue(120)->required(),
            Select::make("{$p}validity_unit")->label('Unit')->options(['months' => 'Months', 'years' => 'Years'])->required()
                ->helperText('1 to 120 months in total.'),
            TextInput::make("{$p}activation_window_minutes")->label('License file expiry window (minutes)')->integer()
                ->minValue(PolicyRules::MIN_WINDOW_MINUTES)->maxValue(PolicyRules::MAX_WINDOW_MINUTES)->required()
                ->helperText('How long after generation the file can be installed: 5 to 180 minutes (3 hours at most).'),
            CheckboxList::make("{$p}features")->label('Features enabled')->options(fn () => FeatureCatalog::options())
                ->columns(2)->bulkToggleable()->columnSpanFull(),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $adminId = auth()->id();

        // Validate every tab first (server-side caps, whatever the browser sent), then save.
        $validated = [];
        foreach (self::policies() as $policy) {
            $key = self::formKey($policy->scope);
            $input = (array) ($this->data[$key] ?? $state[$key] ?? []);
            $inherit = $policy->scope !== LicensePolicy::GLOBAL && (bool) ($input['inherit_global'] ?? false);
            $values = $inherit
                ? $policy->only(['max_licences', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'features'])
                : PolicyRules::validate($input, false, "data.{$key}.");
            $validated[$policy->scope] = ['inherit_global' => $inherit] + array_intersect_key($values, array_flip([
                'max_licences', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'features',
            ]));
        }

        DB::transaction(function () use ($validated, $adminId) {
            foreach (self::policies() as $policy) {
                $new = $validated[$policy->scope];
                $old = $policy->only(array_keys($new));
                $policy->fill($new)->save();
                PolicyAudit::record($adminId, 'license_policy', $policy, $policy->label(), 'updated', $old, $new);
            }
        });

        Notification::make()->title('License policy saved')->body('New licence files use it. Files already issued do not change.')->success()->send();
    }

    /** @return Collection<int, PolicyAuditLog> */
    public function history(): Collection
    {
        return PolicyAuditLog::query()->with('admin')->latest('id')->limit(100)->get();
    }
}
