<?php

namespace App\Filament\Pages;

use App\Models\FeatureCatalog;
use App\Models\LicensePolicy;
use App\Models\PolicyAuditLog;
use App\Models\PricingPlan;
use App\Support\LicensePolicy\PolicyAudit;
use App\Support\LicensePolicy\PolicyRules;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    public function getSubheading(): ?string
    {
        return \App\Support\LicenseFile\Issuer::isConfigured()
            ? null
            : '⚠ Licence files cannot be issued: LICENSE_KEY_ID, LICENSE_SIGNING_PRIVATE_KEY or LICENSE_ENC_KEY is missing or invalid in .env. '
                .'Licence file downloads fail, and consoles activating a licence key get free features only (no Premium Features). '
                .'Set the keys, then run php artisan optimize.';
    }

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
                'inherit_global' => true, 'bundles' => FeatureCatalog::defaultBundles(), 'individual_features' => [],
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
                'inherit_global', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'max_licences', 'install_id_mode',
            ]) + ['premium_bundle' => in_array(FeatureCatalog::BUNDLE_PREMIUM, (array) $policy->bundles, true)];
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

    /**
     * The policy form. Default policies ($custom false) grant premium features only through the
     * Premium Features bundle; a custom policy also allows single premium features.
     *
     * @return array<int, mixed>
     */
    public static function policyFields(string $key = '', bool $custom = false): array
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
            Select::make("{$p}install_id_mode")->label('Installation ID for licence files')
                ->options(['optional' => 'Optional (file works on any one console)', 'required' => 'Required (file works on that console only)'])->required()
                ->helperText('Whether users must enter the console Installation ID when they generate a licence file. Online key activation records it automatically.'),
            // Free features: always on, shown checked and greyed out, never saved.
            CheckboxList::make("{$p}free_features")->label('Free features (always included)')
                ->options(fn () => FeatureCatalog::freeOptions())
                ->formatStateUsing(fn () => array_keys(FeatureCatalog::freeOptions()))
                ->disabled()->dehydrated(false)->columns(2)->columnSpanFull()
                ->helperText('Always enabled in the console. They are never written into a licence and cannot be turned off.'),
            ...($custom ? self::customPremiumFields($p) : self::defaultPremiumFields($p)),
        ];
    }

    /** "Includes NFS Storage, Azure Storage, Remote Tunnel, and any premium features added later." */
    public static function bundleDescription(): string
    {
        $names = array_values(FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));

        return $names === []
            ? 'Includes every premium feature, including ones added later.'
            : 'Includes '.implode(', ', $names).', and any premium features added later.';
    }

    /**
     * Help under the custom policy's premium checkboxes. When every premium feature is picked one
     * by one, suggests the bundle: grants do not grow, the bundle includes later premium features.
     *
     * @param  list<string>  $picked
     */
    public static function individualHint(bool $bundle, array $picked): string
    {
        if ($bundle) {
            return 'Included through Premium Features (all).';
        }
        $all = array_keys(FeatureCatalog::premiumOptions());

        return $all !== [] && array_diff($all, $picked) === []
            ? 'Every premium feature is selected one by one, so later premium features will not be included. Tick "Premium Features (all)" to include them automatically.'
            : 'Granted one by one: premium features added later are not included.';
    }

    /** @return array<int, mixed> default policies: only the bundle */
    private static function defaultPremiumFields(string $p): array
    {
        return [
            Toggle::make("{$p}premium_bundle")->label('Premium Features')
                ->helperText(fn () => self::bundleDescription())
                ->columnSpanFull(),
            CheckboxList::make("{$p}premium_members")->label('Included with Premium Features')
                ->options(fn () => FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM))
                ->formatStateUsing(fn () => array_keys(FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM)))
                ->disabled()->dehydrated(false)->columns(2)->columnSpanFull()
                ->helperText('Single premium features are granted to one user with a custom license (Users › Create custom license).'),
        ];
    }

    /** @return array<int, mixed> custom policies: the bundle, or single premium features */
    private static function customPremiumFields(string $p): array
    {
        $all = fn () => array_keys(FeatureCatalog::premiumOptions());

        return [
            Checkbox::make("{$p}premium_bundle")->label('Premium Features (all)')
                ->helperText(fn () => self::bundleDescription())
                ->live()
                // Ticked: every premium feature shows as included. Unticked again: start from none.
                ->afterStateUpdated(function (bool $state, ?bool $old, Set $set) use ($p, $all) {
                    if ($state) {
                        $set("{$p}individual_features", $all());
                    } elseif ($old) {
                        $set("{$p}individual_features", []);
                    }
                })
                ->columnSpanFull(),
            CheckboxList::make("{$p}individual_features")->label('Premium features')
                ->options(fn () => FeatureCatalog::premiumOptions())
                ->disabled(fn (Get $get) => (bool) $get("{$p}premium_bundle"))
                ->live()->columns(2)->columnSpanFull()
                ->helperText(fn (Get $get) => self::individualHint((bool) $get("{$p}premium_bundle"), (array) $get("{$p}individual_features"))),
            Text::make(fn (Get $get) => 'Premium: '.FeatureCatalog::premiumSummary(
                $get("{$p}premium_bundle") ? [FeatureCatalog::BUNDLE_PREMIUM] : [],
                (array) $get("{$p}individual_features"),
            ))->columnSpanFull(),
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
                ? $policy->only(['max_licences', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'install_id_mode', 'bundles', 'individual_features'])
                : PolicyRules::validate(PolicyRules::fromForm($input), false, "data.{$key}.");
            $validated[$policy->scope] = ['inherit_global' => $inherit] + array_intersect_key($values, array_flip([
                'max_licences', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'activation_window_minutes', 'install_id_mode', 'bundles', 'individual_features',
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
