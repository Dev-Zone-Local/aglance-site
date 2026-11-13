<?php

use App\Models\FeatureCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Licence categories (App\Support\LicensePolicy\LicenseCategory): plan policies no longer choose
 * premium features. Free plans get free features only, premium plans (Enterprise) the Premium
 * Features bundle. Stored plan policies are aligned with that so the admin panel shows what
 * licences get. Limits are not touched; custom licences keep their own choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('license_policies')->get(['id', 'scope']) as $row) {
            $plan = str_starts_with($row->scope, 'plan:') ? substr($row->scope, 5) : null;
            $premium = $plan !== null && config("atglance.plans.{$plan}.tier") === 'premium';

            DB::table('license_policies')->where('id', $row->id)->update([
                'bundles' => json_encode($premium ? [FeatureCatalog::BUNDLE_PREMIUM] : []),
                'individual_features' => json_encode([]),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Features followed the policy form before; there is nothing to restore from.
    }
};
