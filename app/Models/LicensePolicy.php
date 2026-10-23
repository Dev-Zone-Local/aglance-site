<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A default licence policy: scope "global", or "plan:<code>" for one plan. A plan row with
 * inherit_global uses the global policy. Changes apply only to licences generated afterwards.
 */
class LicensePolicy extends Model
{
    public const GLOBAL = 'global';

    protected $fillable = [
        'scope', 'inherit_global', 'max_nodes', 'max_users', 'validity_value', 'validity_unit',
        'activation_window_minutes', 'features',
    ];

    protected function casts(): array
    {
        return [
            'inherit_global' => 'boolean',
            'max_nodes' => 'integer',
            'max_users' => 'integer',
            'validity_value' => 'integer',
            'activation_window_minutes' => 'integer',
            'features' => 'array',
        ];
    }

    public static function global(): self
    {
        return static::query()->firstOrCreate(['scope' => self::GLOBAL], ['features' => FeatureCatalog::defaultKeys()]);
    }

    public static function forPlan(string $plan): ?self
    {
        return static::query()->where('scope', 'plan:'.$plan)->first();
    }

    /** "Global default" or "Free plan". */
    public function label(): string
    {
        if ($this->scope === self::GLOBAL) {
            return 'Global default';
        }
        $plan = substr($this->scope, 5);

        return config("atglance.plans.{$plan}.label", ucfirst($plan)).' plan';
    }

    public function validityMonths(): int
    {
        return $this->validity_value * ($this->validity_unit === 'years' ? 12 : 1);
    }
}
