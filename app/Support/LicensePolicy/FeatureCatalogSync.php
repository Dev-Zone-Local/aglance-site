<?php

namespace App\Support\LicensePolicy;

use App\Models\FeatureCatalog;

/**
 * Adds the feature keys of the shared contract (resources/licensing/features.json, a copy of
 * the workspace contracts/fixtures/features.json) to the feature_catalog table. Existing rows
 * keep the name and description an admin may have edited. Keys no longer in the contract (the
 * module became core, controlled in the console's Plugins) are removed.
 */
class FeatureCatalogSync
{
    /** Tests point this at another contract file. */
    public static ?string $pathOverride = null;

    private static bool $checked = false;

    public static function path(): string
    {
        return self::$pathOverride ?? resource_path('licensing/features.json');
    }

    /**
     * Sync the catalog when the contract file changed since the last sync. A feature added to the
     * contract is then part of the Premium Features bundle at once, without a migration or an admin
     * opening the Feature Catalog. Checked once per request (one file hash and one cache read).
     */
    public static function ensureCurrent(): void
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;

        $hash = @md5_file(self::path());
        if ($hash === false) {
            return;
        }

        try {
            if (\Illuminate\Support\Facades\Cache::get('feature_catalog.contract_hash') === $hash) {
                return;
            }
            if (! \Illuminate\Support\Facades\Schema::hasTable('feature_catalog')) {
                return; // during the first migrations
            }
            self::sync();
            \Illuminate\Support\Facades\Cache::forever('feature_catalog.contract_hash', $hash);
        } catch (\Throwable $e) {
            report($e); // never break a page or the licence API over the catalog
        }
    }

    /** Forget the per-request check (tests, long-running workers). */
    public static function resetCheck(): void
    {
        self::$checked = false;
    }

    /**
     * @return list<array{key: string, name: string, description: string, default: bool}>
     */
    public static function contract(): array
    {
        $data = json_decode((string) @file_get_contents(self::path()), true);

        return array_values(array_filter((array) ($data['features'] ?? []), fn ($f) => is_string($f['key'] ?? null)));
    }

    /**
     * The bundles of the contract (FEATURES.md, "Bundles"): groups of features, not rows of the
     * catalog.
     *
     * @return list<array{key: string, name: string, description?: string, members_tier: string, default?: bool}>
     */
    public static function bundles(): array
    {
        static $cache = [];
        $path = self::path();
        if (! array_key_exists($path, $cache)) {
            $data = json_decode((string) @file_get_contents($path), true);
            $cache[$path] = array_values(array_filter((array) ($data['bundles'] ?? []),
                fn ($b) => is_string($b['key'] ?? null) && is_string($b['name'] ?? null)));
        }

        return $cache[$path];
    }

    /** @return int rows added */
    public static function sync(): int
    {
        $added = 0;
        $hasTier = \Illuminate\Support\Facades\Schema::hasColumn('feature_catalog', 'tier');
        foreach (self::contract() as $sort => $feature) {
            $row = FeatureCatalog::query()->firstOrNew(['key' => $feature['key']]);
            if (! $row->exists) {
                $row->fill([
                    'name' => $feature['name'],
                    'description' => $feature['description'] ?? '',
                    'default_enabled' => (bool) ($feature['default'] ?? true),
                ]);
                $added++;
            }
            // The tier is defined by the contract only (the column exists from the 2026_10_25 migration on).
            if ($hasTier) {
                $row->tier = ($feature['tier'] ?? FeatureCatalog::TIER_PREMIUM) === FeatureCatalog::TIER_FREE ? FeatureCatalog::TIER_FREE : FeatureCatalog::TIER_PREMIUM;
            }
            $row->sort = $sort;
            $row->save();
        }

        $keys = array_column(self::contract(), 'key');
        if ($keys !== []) {
            FeatureCatalog::query()->whereNotIn('key', $keys)->delete();
        }

        return $added;
    }
}
