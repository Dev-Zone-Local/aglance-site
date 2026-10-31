<?php

use App\Models\Doc;
use App\Support\LicensePolicy\FeatureCatalogSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remote Tunnel (remote_tunnel, premium) joins the Feature Catalog from the contract
 * (resources/licensing/features.json), and its help pages are added to existing sites (new sites
 * get them from database/seeders/content/docs.json). Pages that already exist are not touched.
 */
return new class extends Migration
{
    private const SLUGS = ['remote-tunnel', 'agent-tunnel', 'ipsec-vpn', 'direct-connection', 'tunnel-authentication', 'tunnel-troubleshooting'];

    public function up(): void
    {
        FeatureCatalogSync::sync();

        if (! Schema::hasTable('docs') || Doc::query()->count() === 0) {
            return; // the content seeder fills an empty table
        }
        $rows = json_decode((string) file_get_contents(database_path('seeders/content/docs.json')), true) ?: [];
        foreach ($rows as $row) {
            if (in_array($row['slug'] ?? '', self::SLUGS, true) && ! Doc::query()->where('slug', $row['slug'])->exists()) {
                Doc::query()->create($row);
            }
        }
    }

    public function down(): void
    {
        // The catalog follows the contract file; help pages are kept.
    }
};
