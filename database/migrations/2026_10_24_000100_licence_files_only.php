<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licences are licence files only; licence keys are removed from the site.
 *
 * - license_policies / custom_license_policies: max_licences becomes max_files (licence files an
 *   account may hold until they expire), plus require_email_code (emailed 5-digit code before a
 *   file is generated) and install_id_mode (required | optional).
 * - licenses (issued files): files no longer belong to a key (token_id dropped); an admin can
 *   release a file's slot early (released_at, released_by_admin_id).
 *
 * The old licence key tables and columns (personal_access_tokens licence columns,
 * license_activations) are left in place, unused, so no data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->renameColumn('max_licences', 'max_files');
            });
            Schema::table($table, function (Blueprint $t) {
                $t->boolean('require_email_code')->default(true)->after('max_files');
                $t->string('install_id_mode', 10)->default('optional')->after('require_email_code');
            });
        }

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('token_id');
        });
        Schema::table('licenses', function (Blueprint $table) {
            $table->dateTime('released_at')->nullable()->after('install_id');
            $table->foreignId('released_by_admin_id')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by_admin_id');
            $table->dropColumn('released_at');
            $table->foreignId('token_id')->nullable()->after('user_id')->constrained('personal_access_tokens')->nullOnDelete();
        });
        foreach (['license_policies', 'custom_license_policies'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['require_email_code', 'install_id_mode']);
            });
            Schema::table($table, function (Blueprint $t) {
                $t->renameColumn('max_files', 'max_licences');
            });
        }
    }
};
