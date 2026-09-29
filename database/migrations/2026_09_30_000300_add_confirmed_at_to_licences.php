<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A licence request is saved immediately; the user confirms it with the emailed
     * 5-digit code, which also unlocks viewing the key. Existing licences were
     * already created through the code flow, so they count as confirmed.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('key_visible_until');
        });

        DB::table('personal_access_tokens')
            ->where('abilities', 'like', '%"console:license"%')
            ->update(['confirmed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
    }
};
