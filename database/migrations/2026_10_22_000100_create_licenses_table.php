<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline licence files (.atgl) generated on the Licences page. The file itself is not
 * stored: it is signed and encrypted on download. This row records what was issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('license_id', 64)->unique();      // LIC-<uuid>
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('org');
            $table->string('plan', 50);
            $table->unsignedInteger('seats');
            $table->json('features')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('activate_before');
            $table->dateTime('expires_at');
            $table->string('key_id', 64);
            $table->uuid('install_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
