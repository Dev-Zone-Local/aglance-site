<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release files (CLI packages, console installer and update scripts) uploaded under Admin → Downloads.
 * The file itself is on the "downloads" disk at {product}/{folder}/{file_name}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_files', function (Blueprint $table) {
            $table->id();
            $table->string('product', 20);            // cli | console
            $table->string('folder', 60);             // the release version, or a label such as "scripts"
            $table->string('version', 30)->nullable(); // release this file belongs to (null = not tied to a release)
            $table->string('platform', 20);           // linux | windows | macos | any
            $table->string('arch', 20)->nullable();   // amd64, arm64, armhf, x86_64, aarch64 ...
            $table->string('kind', 20);               // package | installer | updater | script
            $table->string('file_name');
            $table->string('path');                   // on the downloads disk
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64);
            $table->string('legacy_path')->nullable(); // path on app.atglance.live, e.g. cli/atglance_2.2/<file>
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->unique(['product', 'folder', 'file_name']);
            $table->index(['product', 'version']);
            $table->index('legacy_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_files');
    }
};
