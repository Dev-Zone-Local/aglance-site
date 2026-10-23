<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A default licence policy: scope "global", or "plan:<code>" for one plan of Admin → Pricing
 * plans (code = the plan name as a slug, the value users carry in users.plan). A plan row with
 * inherit_global uses the global policy. Changes apply only to licences generated afterwards.
 */
class LicensePolicy extends Model
{
    public const GLOBAL = 'global';

    protected $fillable = [
        'scope', 'inherit_global', 'max_nodes', 'max_users', 'validity_value', 'validity_unit',
        'activation_window_minutes', 'max_licences', 'install_id_mode', 'premium_features', 'features',
    ];

    protected function casts(): array
    {
        return [
            'inherit_global' => 'boolean',
            'max_nodes' => 'integer',
            'max_users' => 'integer',
            'validity_value' => 'integer',
            'activation_window_minutes' => 'integer',
            'max_licences' => 'integer',
            'premium_features' => 'boolean',
            'features' => 'array',
        ];
    }

    public static function global(): self
    {
        return static::query()->firstOrCreate(['scope' => self::GLOBAL], ['premium_features' => FeatureCatalog::defaultKeys() !== []]);
    }

    /** The plan's default policy, only while the plan exists in Admin → Pricing plans. */
    public static function forPlan(string $plan): ?self
    {
        if (! PricingPlan::query()->get()->contains(fn (PricingPlan $p) => $p->code() === $plan)) {
            return null;
        }

        return static::query()->where('scope', 'plan:'.$plan)->first();
    }

    /** "Global default" or "Enterprise plan". */
    public function label(): string
    {
        if ($this->scope === self::GLOBAL) {
            return 'Global default';
        }
        $code = substr($this->scope, 5);
        $plan = PricingPlan::query()->get()->first(fn (PricingPlan $p) => $p->code() === $code);

        return ($plan?->name ?? ucfirst($code)).' plan';
    }

    public function validityMonths(): int
    {
        return $this->validity_value * ($this->validity_unit === 'years' ? 12 : 1);
    }
}
