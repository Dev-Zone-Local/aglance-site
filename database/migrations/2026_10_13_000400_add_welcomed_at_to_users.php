<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** When the one-time welcome email went out (User::sendWelcomeSafely). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('welcomed_at')->nullable()->after('email_verified_at');
        });

        // Accounts verified before this feature count as welcomed, so re-verifying them sends nothing.
        DB::table('users')->whereNotNull('email_verified_at')->update(['welcomed_at' => DB::raw('email_verified_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('welcomed_at');
        });
    }
};
