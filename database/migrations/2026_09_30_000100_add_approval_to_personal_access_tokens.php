<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Licences (tokens with the console:license ability) need admin approval before a
     * Management Console can activate them. Licences that already exist are approved
     * so running installations keep working.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable()->after('expires_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        DB::table('personal_access_tokens')
            ->where('abilities', 'like', '%"console:license"%')
            ->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
