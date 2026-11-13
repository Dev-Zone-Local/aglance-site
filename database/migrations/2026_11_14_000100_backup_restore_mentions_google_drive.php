<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Google Drive backups are free and run under the backup_restore feature (the console's Google
 * Drive plugin requires that key). The Feature Catalog row now says so. FeatureCatalogSync keeps
 * descriptions an admin edited, so only the untouched contract text is replaced here.
 */
return new class extends Migration
{
    private const OLD = 'Scheduled backups and restore of the console.';

    private const NEW = 'Scheduled backups and restore of the console: on the console server, S3, or your own Google Drive (NFS and Azure Storage are premium).';

    public function up(): void
    {
        DB::table('feature_catalog')->where('key', 'backup_restore')->where('description', self::OLD)
            ->update(['description' => self::NEW, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('feature_catalog')->where('key', 'backup_restore')->where('description', self::NEW)
            ->update(['description' => self::OLD, 'updated_at' => now()]);
    }
};
