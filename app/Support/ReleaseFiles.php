<?php

namespace App\Support;

use App\Models\DownloadFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Files of each release (CLI packages for Linux and Windows, console installer and update scripts).
 *
 * Admins upload them under Admin → Downloads. They are stored once, on the "downloads" disk at
 * {product}/{folder}/{file_name}, and served from:
 *   - https://atglance.live/downloads/files/{product}/{folder}/{file_name}
 *   - https://atglance.live/downloads/latest/{product}/{file_name}   (newest file with that name)
 *   - the old app.atglance.live paths (legacy_path), e.g. /cli/atglance_2.2/<file>, /console/atglance-installer.sh
 *
 * Every page that shows a download (/downloads, /cli, /console, release notes, the dashboard
 * installer, /api/downloads) reads the files of the latest release from here.
 */
class ReleaseFiles
{
    public const DISK = 'downloads';

    /** Old app.atglance.live links that pointed at the wrong name. */
    public const LEGACY_ALIASES = [
        'console/atglance-updater.sh' => 'console/atglance_update.sh',
        'console/atglance-updater.ps1' => 'console/atglance_update.ps1',
    ];

    private const ARCH_ORDER = ['amd64' => 1, 'x86_64' => 1, 'arm64' => 2, 'aarch64' => 2, 'armhf' => 3, 'universal' => 0];

    /** Allowed upload extensions. */
    public const EXTENSIONS = ['deb', 'rpm', 'gz', 'tgz', 'zip', 'msi', 'exe', 'pkg', 'dmg', 'sh', 'ps1', 'txt'];

    /**
     * Platform, architecture, kind and version read from a file name.
     *
     * @return array{platform: string, arch: ?string, kind: string, version: ?string}
     */
    public static function detect(string $fileName): array
    {
        $n = strtolower($fileName);

        $platform = match (true) {
            (bool) preg_match('/\.(msi|exe|ps1)$/', $n), str_contains($n, 'windows') => 'windows',
            (bool) preg_match('/\.(pkg|dmg)$/', $n), str_contains($n, 'darwin'), str_contains($n, 'macos') => 'macos',
            (bool) preg_match('/\.(deb|rpm|tar\.gz|tgz|sh)$/', $n), str_contains($n, 'linux') => 'linux',
            default => 'any',
        };

        preg_match('/(amd64|x86_64|arm64|aarch64|armhf|armv7|universal|386|i686)/', $n, $m);
        $arch = $m[1] ?? null;

        $kind = match (true) {
            str_contains($n, 'install') => 'installer',
            str_contains($n, 'update') => 'updater',
            (bool) preg_match('/\.(sh|ps1|txt)$/', $n) => 'script',
            default => 'package',
        };

        preg_match('/(\d+\.\d+(?:\.\d+)?)/', $n, $v);

        return ['platform' => $platform, 'arch' => $arch, 'kind' => $kind, 'version' => $v[1] ?? null];
    }

    /** Where app.atglance.live kept a file: cli/atglance_2.2/<file>, console/<file>. */
    public static function defaultLegacyPath(string $product, ?string $version, string $fileName): ?string
    {
        if ($product === 'console') {
            return "console/{$fileName}";
        }
        if ($version && preg_match('/^(\d+)\.(\d+)/', $version, $m)) {
            return "cli/atglance_{$m[1]}.{$m[2]}/{$fileName}";
        }

        return null;
    }

    /**
     * Copy a file onto the downloads disk and record it. Uploading the same name to the same
     * folder replaces the old file.
     */
    public static function store(string $product, ?string $version, string $sourcePath, string $fileName, array $options = []): DownloadFile
    {
        $fileName = self::cleanName($fileName);
        abort_unless(self::allowed($fileName), 422, "File type not allowed: {$fileName}");
        $folder = $options['folder'] ?? ($version ?: 'scripts');
        $detected = self::detect($fileName);
        $path = "{$product}/{$folder}/{$fileName}";

        $stream = fopen($sourcePath, 'rb');
        try {
            Storage::disk(self::DISK)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $full = Storage::disk(self::DISK)->path($path);

        return DownloadFile::updateOrCreate(
            ['product' => $product, 'folder' => $folder, 'file_name' => $fileName],
            [
                'version' => $version,
                'platform' => $options['platform'] ?? $detected['platform'],
                'arch' => $options['arch'] ?? $detected['arch'],
                'kind' => $options['kind'] ?? $detected['kind'],
                'path' => $path,
                'size' => filesize($full) ?: 0,
                'sha256' => hash_file('sha256', $full),
                'legacy_path' => array_key_exists('legacy_path', $options)
                    ? $options['legacy_path']
                    : self::defaultLegacyPath($product, $version, $fileName),
            ],
        );
    }

    public static function delete(DownloadFile $file): void
    {
        Storage::disk(self::DISK)->delete($file->path);
        $file->delete();
    }

    /** Files of one release, Linux first, then by architecture and name. */
    public static function forRelease(string $product, ?string $version): Collection
    {
        if (! $version) {
            return collect();
        }

        return self::sort(DownloadFile::where('product', $product)->where('version', $version)->get());
    }

    /** Files of the latest published release of a product. */
    public static function latest(string $product): Collection
    {
        return self::forRelease($product, Downloads::all()[$product]['releases'][0]['version'] ?? null);
    }

    /** @return array<string, Collection> version => files, for every release that has files */
    public static function byVersion(string $product): array
    {
        return DownloadFile::where('product', $product)->whereNotNull('version')->get()
            ->groupBy('version')->map(fn ($g) => self::sort($g))->all();
    }

    /** The file most people want on a platform: amd64 .deb on Linux, .msi then .zip on Windows. */
    public static function primary(Collection $files, string $platform, ?string $kind = null): ?DownloadFile
    {
        $candidates = $files->where('platform', $platform)
            ->when($kind, fn ($c) => $c->where('kind', $kind), fn ($c) => $c->whereIn('kind', ['package', 'installer']));

        // Most Macs sold now are Apple silicon, so arm64 comes first there.
        $archOrder = $platform === 'macos' ? ['universal' => 0, 'arm64' => 1, 'amd64' => 2] : self::ARCH_ORDER;
        $rank = function (DownloadFile $f) use ($archOrder): int {
            $n = strtolower($f->file_name);
            $ext = match (true) {
                str_ends_with($n, '.deb'), str_ends_with($n, '.msi'), str_ends_with($n, '.pkg') => 0,
                str_ends_with($n, '.zip'), str_ends_with($n, '.rpm'), str_ends_with($n, '.tar.gz') => 1,
                default => 2,
            };

            return $ext * 10 + ($archOrder[$f->arch] ?? 5);
        };

        return $candidates->sortBy($rank)->first();
    }

    /** A file at its old app.atglance.live path (newest upload wins), or null. */
    public static function findLegacy(string $path): ?DownloadFile
    {
        $path = trim($path, '/');
        $path = self::LEGACY_ALIASES[$path] ?? $path;

        return DownloadFile::where('legacy_path', $path)->orderByDesc('id')->first();
    }

    /** Files that lived in one old folder, e.g. cli/atglance_2.2 (for its SHA256SUMS). */
    public static function inLegacyFolder(string $folder): Collection
    {
        $folder = trim($folder, '/');

        return self::sort(DownloadFile::where('legacy_path', 'like', $folder.'/%')->get()
            ->filter(fn ($f) => dirname($f->legacy_path) === $folder));
    }

    /** sha256sum format: "<hash>  <file>" per line. */
    public static function checksums(Collection $files): string
    {
        return $files->map(fn ($f) => "{$f->sha256}  {$f->file_name}")->implode("\n")."\n";
    }

    public static function respond(DownloadFile $file): BinaryFileResponse
    {
        $full = Storage::disk(self::DISK)->path($file->path);
        abort_unless(is_file($full), 404);

        DownloadFile::whereKey($file->id)->increment('download_count');

        $script = (bool) preg_match('/\.(sh|ps1|txt)$/i', $file->file_name);
        $response = new BinaryFileResponse($full, 200, [
            'Content-Type' => $script ? 'text/plain; charset=utf-8' : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
        // Scripts open inline so "curl ... | bash" and "irm ... | iex" work; packages download.
        $response->setContentDisposition($script ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->file_name);

        return $response;
    }

    /**
     * Release file types an admin may upload (no PHP, HTML or other files a server could run).
     * SHA256SUMS is not uploaded: it is built from the stored files.
     */
    public static function allowed(string $name): bool
    {
        $n = strtolower(basename($name));
        // The plain macOS binary has no extension: atglance_3.0.0_darwin_arm64.
        if (preg_match('/^atglance[\w.+-]*_darwin_(arm64|amd64|universal)$/', $n)) {
            return true;
        }
        $ext = str_ends_with($n, '.tar.gz') ? 'gz' : pathinfo($n, PATHINFO_EXTENSION);

        return in_array($ext, self::EXTENSIONS, true);
    }

    public static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._+-]/', '_', $name);
        abort_if($name === '' || str_starts_with($name, '.'), 422, 'Invalid file name.');

        return $name;
    }

    private static function sort(Collection $files): Collection
    {
        $platform = ['linux' => 0, 'windows' => 1, 'macos' => 2, 'any' => 3];
        $kind = ['package' => 0, 'installer' => 1, 'updater' => 2, 'script' => 3];

        // Within a platform: .deb, .rpm, .tar.gz (or .msi, .zip, .exe), then by architecture.
        $type = fn (string $n) => match (true) {
            str_ends_with($n, '.deb'), str_ends_with($n, '.msi') => 0,
            str_ends_with($n, '.rpm'), str_ends_with($n, '.zip') => 1,
            str_ends_with($n, '.tar.gz'), str_ends_with($n, '.exe') => 2,
            default => 3,
        };

        return $files->sortBy(fn (DownloadFile $f) => sprintf('%d-%d-%d-%d-%s',
            $platform[$f->platform] ?? 9, $kind[$f->kind] ?? 9, $type(strtolower($f->file_name)), self::ARCH_ORDER[$f->arch] ?? 5, $f->file_name))->values();
    }
}
