<?php

namespace Tests\Feature;

use App\Models\Doc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /docs/cli follows the CLI's docs/commands.md (copied to content/docs/cli.md). */
class CliDocTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_cli_reference_lists_every_v2_command(): void
    {
        $res = $this->get('/docs/cli')->assertOk();

        foreach ([
            'atglance status', 'atglance service &lt;name&gt;', 'atglance export', 'atglance watch', 'atglance version --json',
            'atglance login [--server URL]', 'atglance logout', 'atglance whoami', 'atglance check', 'atglance check --scheduled',
            'atglance schedule enable|disable|status', 'atglance system register', 'atglance system status', 'atglance system rotate-key',
            'atglance system enroll', 'atglance system service-account set', 'atglance config backup', 'atglance config list',
            'atglance config pull', 'atglance config diff', 'atglance config restore', 'atglance sync', 'atglance security events',
            'atglance security lockouts', 'atglance agent install',
        ] as $command) {
            $res->assertSee($command, false);
        }

        // Contents list and section anchors.
        $res->assertSee('class="doc-toc"', false)
            ->assertSee('id="exit-codes"', false)
            ->assertSee('href="#workflows"', false);
        // The old 1.x flag table is gone; old flags only appear in the "still work" table.
        $res->assertDontSee('strict `main()` routing order')->assertDontSee('config-imports/');
    }

    public function test_migration_updates_an_existing_site(): void
    {
        Doc::where('slug', 'cli')->update(['content' => '# Old 1.x text']);

        $migration = require database_path('migrations/2026_11_13_000200_update_cli_reference_doc.php');
        $migration->up();

        $this->assertSame(file_get_contents(base_path('content/docs/cli.md')), Doc::firstWhere('slug', 'cli')->content);
    }

    public function test_seed_data_matches_the_content_file(): void
    {
        $docs = json_decode(file_get_contents(database_path('seeders/content/docs.json')), true);
        $cli = collect($docs)->firstWhere('slug', 'cli');

        $this->assertSame(file_get_contents(base_path('content/docs/cli.md')), $cli['content']);
    }
}
