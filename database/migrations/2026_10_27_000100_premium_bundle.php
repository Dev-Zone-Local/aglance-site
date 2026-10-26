<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Premium Features" is a bundle, not a feature (contracts FEATURES.md, "Bundles").
 *
 *   license_policies, custom_license_policies  features -> bundles + individual_features. The old
 *       "premium" key becomes bundles ["premium"]; real keys stay individual grants. Default (global
 *       and plan) policies grant premium features only through the bundle: a default policy with
 *       individual grants gets the bundle instead.
 *   licenses (licence files)  bundles added next to features (a record of what was issued); "premium" moves.
 *   feature_catalog  the "premium" row is removed: a bundle is not a feature.
 */
return new class extends Migration
{
    private const BUNDLE = 'premium';

    public function up(): void
    {
        foreach (['license_policies' => false, 'custom_license_policies' => true] as $table => $custom) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'bundles')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->json('bundles')->nullable()->after('features');
            });
            Schema::table($table, function (Blueprint $t) {
                $t->renameColumn('features', 'individual_features');
            });

            DB::table($table)->select(['id', 'individual_features'])->orderBy('id')->each(function ($row) use ($table, $custom) {
                $old = $this->decode($row->individual_features);
                $bundles = in_array(self::BUNDLE, $old, true) ? [self::BUNDLE] : [];
                $individual = array_values(array_diff($old, [self::BUNDLE]));
                if (! $custom && $individual !== []) {
                    // Default policies offer only the bundle toggle: single grants become the bundle.
                    $bundles = [self::BUNDLE];
                }
                DB::table($table)->where('id', $row->id)->update([
                    'bundles' => json_encode($bundles),
                    // With the bundle, every premium feature is included: no separate grants.
                    'individual_features' => json_encode($bundles !== [] ? [] : $individual),
                ]);
            });
        }

        if (Schema::hasTable('licenses') && ! Schema::hasColumn('licenses', 'bundles')) {
            Schema::table('licenses', function (Blueprint $t) {
                $t->json('bundles')->nullable()->after('features');
            });
            DB::table('licenses')->select(['id', 'features'])->orderBy('id')->each(function ($row) {
                $old = $this->decode($row->features);
                DB::table('licenses')->where('id', $row->id)->update([
                    'bundles' => json_encode(in_array(self::BUNDLE, $old, true) ? [self::BUNDLE] : []),
                    'features' => json_encode(array_values(array_diff($old, [self::BUNDLE]))),
                ]);
            });
        }

        if (Schema::hasTable('feature_catalog')) {
            DB::table('feature_catalog')->where('key', self::BUNDLE)->delete();
        }
    }

    public function down(): void
    {
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'bundles')) {
                continue;
            }
            DB::table($table)->select(['id', 'bundles', 'individual_features'])->orderBy('id')->each(function ($row) use ($table) {
                $features = array_values(array_unique(array_merge($this->decode($row->bundles), $this->decode($row->individual_features))));
                DB::table($table)->where('id', $row->id)->update(['individual_features' => json_encode($features)]);
            });
            Schema::table($table, function (Blueprint $t) {
                $t->renameColumn('individual_features', 'features');
            });
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('bundles');
            });
        }

        if (Schema::hasTable('licenses') && Schema::hasColumn('licenses', 'bundles')) {
            DB::table('licenses')->select(['id', 'bundles', 'features'])->orderBy('id')->each(function ($row) {
                $features = array_values(array_unique(array_merge($this->decode($row->bundles), $this->decode($row->features))));
                DB::table('licenses')->where('id', $row->id)->update(['features' => json_encode($features)]);
            });
            Schema::table('licenses', function (Blueprint $t) {
                $t->dropColumn('bundles');
            });
        }
        // The feature_catalog "premium" row is not restored: the catalog sync follows the contract.
    }

    /** @return list<string> */
    private function decode(mixed $json): array
    {
        $value = is_string($json) ? json_decode($json, true) : null;

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
};
