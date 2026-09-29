<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A licence (Sanctum token with the console:license ability) is bound to exactly
     * one Management Console instance. The row disappears when the licence is revoked.
     */
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_id')->unique()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('instance_id', 100);
            $table->string('hostname')->nullable();
            $table->string('console_version', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('activated_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
