<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Free vs premium tiers (contracts FEATURES.md). The Feature Catalog gets a tier; a licence
 * policy keeps the premium features it enables in `features` (a list of premium keys) instead
 * of the single premium_features flag. Free features are always on and never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('feature_catalog', 'tier')) {
            Schema::table('feature_catalog', function (Blueprint $t) {
                $t->string('tier', 10)->default('premium')->after('description');
            });
        }

        foreach (['license_policies', 'custom_license_policies'] as $table) {
            if (! Schema::hasColumn($table, 'premium_features')) {
                continue;
            }
            DB::table($table)->select(['id', 'premium_features'])->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update([
                    'features' => json_encode($row->premium_features ? ['premium'] : []),
                ]);
            });
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('premium_features');
            });
        }

        // Fill the tiers (and any new keys) from the contract.
        \App\Support\LicensePolicy\FeatureCatalogSync::sync();
    }

    public function down(): void
    {
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            if (Schema::hasColumn($table, 'premium_features')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('premium_features')->default(false)->after('install_id_mode');
            });
            DB::table($table)->select(['id', 'features'])->orderBy('id')->each(function ($row) use ($table) {
                $features = json_decode((string) $row->features, true);
                DB::table($table)->where('id', $row->id)->update([
                    'premium_features' => is_array($features) && in_array('premium', $features, true),
                ]);
            });
        }

        if (Schema::hasColumn('feature_catalog', 'tier')) {
            Schema::table('feature_catalog', function (Blueprint $t) {
                $t->dropColumn('tier');
            });
        }
    }
};
