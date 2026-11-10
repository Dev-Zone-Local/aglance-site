<?php

use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Agent Monitoring (agent_monitoring, premium) joins the Feature Catalog from the contract
 * (resources/licensing/features.json). The Premium Features bundle includes it through its tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        FeatureCatalogSync::sync();
    }

    public function down(): void
    {
        // The catalog follows the contract file.
    }
};
