<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The console's features and their tier. Keys and tiers come from the shared contract
 * (FEATURES.md, synced to resources/licensing/features.json) and are never created, renamed,
 * removed or re-tiered here; admins may edit the display name and description.
 *
 *   free     always on in the console, never written into a licence, not toggleable in a policy
 *   premium  on only when listed in the licence's features[], toggled per licence policy
 */
class FeatureCatalog extends Model
{
    protected $table = 'feature_catalog';

    public const TIER_FREE = 'free';

    public const TIER_PREMIUM = 'premium';

    protected $fillable = ['key', 'name', 'description', 'tier', 'default_enabled', 'sort'];

    protected function casts(): array
    {
        return ['default_enabled' => 'boolean', 'sort' => 'integer'];
    }

    /** @return array<string, string> key => display name, in catalog order */
    public static function options(): array
    {
        return static::query()->orderBy('sort')->pluck('name', 'key')->all();
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return static::query()->orderBy('sort')->pluck('key')->all();
    }

    /** @return list<string> premium keys enabled in new default policies */
    public static function defaultKeys(): array
    {
        return static::query()->where('tier', self::TIER_PREMIUM)->where('default_enabled', true)->orderBy('sort')->pluck('key')->all();
    }

    /** @return array<string, string> premium key => display name: the keys a licence can carry */
    public static function premiumOptions(): array
    {
        return static::query()->where('tier', self::TIER_PREMIUM)->orderBy('sort')->pluck('name', 'key')->all();
    }

    /** @return list<string> */
    public static function premiumKeys(): array
    {
        return array_keys(self::premiumOptions());
    }

    /** @return array<string, string> free key => display name: always included */
    public static function freeOptions(): array
    {
        return static::query()->where('tier', self::TIER_FREE)->orderBy('sort')->pluck('name', 'key')->all();
    }

    /**
     * The premium keys of $features that the catalog knows, in catalog order. Free and unknown
     * keys are dropped: free features are implicit and never go into a licence.
     *
     * @param  array<int, mixed>  $features
     * @return list<string>
     */
    public static function onlyPremium(array $features): array
    {
        return array_values(array_intersect(self::premiumKeys(), array_filter($features, 'is_string')));
    }
}
