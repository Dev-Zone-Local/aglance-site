<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The plain licence key stays viewable (encrypted at rest) for a short window after creation.
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->text('plain_key')->nullable()->after('token');
            $table->dateTime('key_visible_until')->nullable()->after('plain_key');
        });

        // Licences created before the "install token" -> "licence" rename used the old ability,
        // so the admin never saw them. Bring them over; they stay unapproved (under review).
        DB::table('personal_access_tokens')
            ->where('abilities', 'like', '%"console:install"%')
            ->update(['abilities' => json_encode(['console:license'])]);
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['plain_key', 'key_visible_until']);
        });
    }
};
