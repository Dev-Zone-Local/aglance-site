<?php

use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Migrations\Migration;

/**
 * "Add servers from the console" (remote_deploy, premium) joins the Feature Catalog from the
 * contract. From now on the catalog also re-syncs by itself when the contract file changes
 * (FeatureCatalogSync::ensureCurrent), so later features need no migration like this one.
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
