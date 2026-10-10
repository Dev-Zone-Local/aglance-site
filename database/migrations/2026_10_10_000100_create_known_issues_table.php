<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Known problems with their solutions, shown on /known-problems and the install guide. */
    public function up(): void
    {
        Schema::create('known_issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('product', 20)->index();      // cli | console
            $table->json('platforms')->nullable();        // ["linux","windows"]; empty = every platform
            $table->json('tags')->nullable();             // free labels, e.g. ["proxy","https"]
            $table->text('symptom')->nullable();          // "What you see"
            $table->longText('solution');                 // Markdown
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_issues');
    }
};
