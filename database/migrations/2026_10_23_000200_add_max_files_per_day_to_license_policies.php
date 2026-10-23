<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licence files one account may generate per day: set on the global default policy
 * (Admin → License Policy), replacing the fixed config value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_files_per_day')->default(20)->after('activation_window_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('license_policies', function (Blueprint $table) {
            $table->dropColumn('max_files_per_day');
        });
    }
};
