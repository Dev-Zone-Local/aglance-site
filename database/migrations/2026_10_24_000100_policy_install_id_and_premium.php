<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licence policy: whether a licence file needs the console's Installation ID (install_id_mode:
 * required | optional), and one feature flag, premium_features, which puts the "premium" key
 * (contracts FEATURES.md) into licence files. The per-feature checklist is no longer used:
 * current console features are core and controlled in the console's Plugins.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('install_id_mode', 10)->default('optional')->after('max_licences');
                $t->boolean('premium_features')->default(false)->after('install_id_mode');
            });
        }
    }

    public function down(): void
    {
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['install_id_mode', 'premium_features']);
            });
        }
    }
};
