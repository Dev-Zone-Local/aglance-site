<?php

namespace App\Models;

use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Eloquent\Model;

/**
 * The console's features and their tier. Keys and tiers come from the shared contract
 * (FEATURES.md, synced to resources/licensing/features.json) and are never created, renamed,
 * removed or re-tiered here; admins may edit the display name and description.
 *
 *   free     always on in the console, never written into a licence, not toggleable in a policy
 *   premium  on when the licence grants it: through a bundle (bundles[], e.g. "premium" = every
 *            premium feature, present and future) or one by one (features[], custom policies)
 *
 * Bundles (FEATURES.md, "Bundles") are groups, not features: they are not rows here and their
 * keys never go into features[]. They come from the contract file (FeatureCatalogSync::bundles()).
 */
class FeatureCatalog extends Model
{
    protected $table = 'feature_catalog';

    public const TIER_FREE = 'free';

    public const TIER_PREMIUM = 'premium';

    /** Premium Features: the bundle with every premium-tier feature. */
    public const BUNDLE_PREMIUM = 'premium';

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

    /** @return list<string> bundles enabled in new default policies (contract "default") */
    public static function defaultBundles(): array
    {
        return array_values(array_column(array_filter(FeatureCatalogSync::bundles(), fn (array $b) => (bool) ($b['default'] ?? false)), 'key'));
    }

    /** @return array<string, string> bundle key => display name */
    public static function bundleOptions(): array
    {
        return array_column(FeatureCatalogSync::bundles(), 'name', 'key');
    }

    /** @return list<string> */
    public static function bundleKeys(): array
    {
        return array_keys(self::bundleOptions());
    }

    /**
     * The members of a bundle known to this site: every feature of its tier. The console resolves
     * them itself from its own catalog, so a newer console may know more.
     *
     * @return array<string, string> key => display name
     */
    public static function bundleMembers(string $bundle): array
    {
        foreach (FeatureCatalogSync::bundles() as $b) {
            if ($b['key'] === $bundle) {
                return ($b['members_tier'] ?? self::TIER_PREMIUM) === self::TIER_FREE ? self::freeOptions() : self::premiumOptions();
            }
        }

        return [];
    }

    /**
     * The known bundles of $bundles, in contract order.
     *
     * @param  array<int, mixed>  $bundles
     * @return list<string>
     */
    public static function onlyBundles(array $bundles): array
    {
        return array_values(array_intersect(self::bundleKeys(), array_filter($bundles, 'is_string')));
    }

    /**
     * One line for admins and users: "All (bundle)", "NFS Storage only", "NFS Storage, X" or "None".
     *
     * @param  list<string>  $bundles
     * @param  list<string>  $features  individual grants
     */
    public static function premiumSummary(array $bundles, array $features): string
    {
        if (in_array(self::BUNDLE_PREMIUM, $bundles, true)) {
            return 'All (bundle)';
        }
        $names = array_values(array_intersect_key(self::premiumOptions(), array_flip($features)));

        return match (count($names)) {
            0 => 'None',
            1 => $names[0].' only',
            default => implode(', ', $names),
        };
    }

    /** @return array<string, string> premium feature key => display name: the keys a licence can grant one by one */
    public static function premiumOptions(): array
    {
        return array_diff_key(
            static::query()->where('tier', self::TIER_PREMIUM)->orderBy('sort')->pluck('name', 'key')->all(),
            array_flip(self::bundleKeys()),
        );
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
     * The premium keys of $features that the catalog knows, in catalog order. Free, unknown and
     * bundle keys are dropped: free features are implicit and a bundle is never a feature.
     *
     * @param  array<int, mixed>  $features
     * @return list<string>
     */
    public static function onlyPremium(array $features): array
    {
        return array_values(array_intersect(self::premiumKeys(), array_filter($features, 'is_string')));
    }
}
