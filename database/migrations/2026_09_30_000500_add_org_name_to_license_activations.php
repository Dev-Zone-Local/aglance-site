<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization name reported by the Management Console; shown to users instead of
     * console identifiers so they can tell which organization a licence is used by.
     */
    public function up(): void
    {
        Schema::table('license_activations', function (Blueprint $table) {
            $table->string('org_name')->nullable()->after('instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('license_activations', function (Blueprint $table) {
            $table->dropColumn('org_name');
        });
    }
};
