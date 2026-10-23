<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One licence, two ways to use it: the licence key (online activation) or a licence file
 * downloaded for that licence (offline). Both follow the same policy.
 *
 * - license_policies / custom_license_policies: max_licences, the total number of licences a
 *   user may hold (at least 1). Raising it is done by AtGlance Support.
 * - licenses (issued files): token_id links each file to its licence. Files issued before this
 *   change had no licence (test data); they are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_policies', function (Blueprint $table) {
            $table->unsignedInteger('max_licences')->default(1)->after('activation_window_minutes');
        });
        Schema::table('custom_license_policies', function (Blueprint $table) {
            $table->unsignedInteger('max_licences')->default(1)->after('activation_window_minutes');
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('token_id')->nullable()->after('user_id')->constrained('personal_access_tokens')->nullOnDelete();
        });
        DB::table('licenses')->whereNull('token_id')->delete();
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('token_id');
        });
        Schema::table('custom_license_policies', function (Blueprint $table) {
            $table->dropColumn('max_licences');
        });
        Schema::table('license_policies', function (Blueprint $table) {
            $table->dropColumn('max_licences');
        });
    }
};
