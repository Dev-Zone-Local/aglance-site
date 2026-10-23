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
    public static function path(): string
    {
        return resource_path('licensing/features.json');
    }

    /**
     * @return list<array{key: string, name: string, description: string, default: bool}>
     */
    public static function contract(): array
    {
        $data = json_decode((string) @file_get_contents(self::path()), true);

        return array_values(array_filter((array) ($data['features'] ?? []), fn ($f) => is_string($f['key'] ?? null)));
    }

    /** @return int rows added */
    public static function sync(): int
    {
        $added = 0;
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
