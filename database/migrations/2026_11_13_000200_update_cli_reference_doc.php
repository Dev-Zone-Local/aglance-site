<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * /docs/cli: replace the 1.x flag list with the v2 command reference (content/docs/cli.md,
 * taken from the CLI's docs/commands.md). The seeder only fills an empty docs table, so
 * existing sites get the new text here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $content = @file_get_contents(base_path('content/docs/cli.md'));
        if ($content === false) {
            return;
        }

        DB::table('docs')->where('slug', 'cli')->update(['content' => $content, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The old 1.x text is in git history (database/seeders/content/docs.json); nothing to undo.
    }
};
