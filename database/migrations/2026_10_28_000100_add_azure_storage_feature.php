<?php

use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Migrations\Migration;

/**
 * Azure Storage (azure_storage, premium) joins the Feature Catalog from the contract
 * (resources/licensing/features.json). Bundle holders get it with no licence change; custom
 * policies can grant it on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        FeatureCatalogSync::sync();
    }

    public function down(): void
    {
        // The catalog follows the contract file; nothing to undo.
    }
};
