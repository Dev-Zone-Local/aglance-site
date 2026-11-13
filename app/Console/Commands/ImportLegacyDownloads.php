<?php

namespace App\Console\Commands;

use App\Support\Downloads;
use App\Support\ReleaseFiles;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * One-time move of the old app.atglance.live files into the downloads store. Every file keeps
 * its old path (legacy_path), so links such as app.atglance.live/cli/atglance_2.2/<file> and
 * app.atglance.live/console/atglance-installer.sh keep working once that host points at this app.
 *
 *   php artisan downloads:import-legacy /www/wwwroot/app.atglance.live
 *
 * Running it again replaces the files with the same names; nothing is duplicated.
 */
class ImportLegacyDownloads extends Command
{
    protected $signature = 'downloads:import-legacy {dir : Folder of the old app.atglance.live site}
        {--console-version= : Release the console scripts belong to (default: the latest console release)}
        {--dry-run : List what would be imported}';

    protected $description = 'Import the old app.atglance.live download files, keeping their old paths';

    public function handle(): int
    {
        $root = rtrim(str_replace('\\', '/', (string) $this->argument('dir')), '/');
        if (! is_dir($root)) {
            $this->error("Not a folder: {$root}");

            return self::FAILURE;
        }

        $consoleVersion = $this->option('console-version') ?: (Downloads::all()['console']['releases'][0]['version'] ?? null);
        $rows = [];

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = ltrim(substr(str_replace('\\', '/', $file->getPathname()), strlen($root)), '/');
            $name = $file->getFilename();
            if (str_starts_with($rel, '.') || str_contains($rel, '/.') || ! ReleaseFiles::allowed($name)) {
                continue;
            }

            [$product, $version, $folder] = $this->place($rel, $name, $consoleVersion);
            if (! $product) {
                continue;
            }

            $rows[] = [$rel, $product, $version ?? '-', $folder];
            if (! $this->option('dry-run')) {
                ReleaseFiles::store($product, $version, $file->getPathname(), $name, ['folder' => $folder, 'legacy_path' => $rel]);
            }
        }

        $this->table(['Old path', 'Product', 'Version', 'Folder'], $rows);
        $this->info(($this->option('dry-run') ? 'Would import ' : 'Imported ').count($rows).' file(s).');

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: ?string, 2: string} product, version, folder */
    private function place(string $rel, string $name, ?string $consoleVersion): array
    {
        $parts = explode('/', $rel);
        $top = $parts[0];
        $sub = count($parts) > 2 ? $parts[1] : null;

        if ($top === 'cli') {
            // Packages carry their version in the name: atglance_2.2.0_amd64.deb, atglance_1.2.deb.
            if (($sub === null || str_starts_with($sub, 'atglance_')) && ($v = ReleaseFiles::detect($name)['version'])) {
                return ['cli', $v, $v];
            }

            return ['cli', null, strtolower($sub ?? 'scripts')];
        }

        if ($top === 'console') {
            if ($sub !== null) {
                return ['console', null, strtolower($sub)]; // e.g. Old-V1
            }

            return ['console', $consoleVersion, $consoleVersion ?: 'scripts'];
        }

        return [null, null, ''];
    }
}
