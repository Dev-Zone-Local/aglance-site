<?php

namespace App\Support;

use App\Models\DownloadFile;

/**
 * The files of the newest CLI release as a machine-readable list, for the Management Console's
 * "add a server" flow (GET /api/releases/cli/latest).
 */
class CliReleaseManifest
{
    private const ARCHES = ['x86_64' => 'amd64', 'amd64' => 'amd64', 'aarch64' => 'arm64', 'arm64' => 'arm64', 'armv7' => 'armhf', 'armhf' => 'armhf'];

    private const ARCH = 'x86_64|amd64|aarch64|arm64|armv7|armhf';

    /**
     * Operating system, CPU and format of a CLI file name, or null when it is not a CLI file.
     *
     * @return array{os: string, arch: string, format: string}|null
     */
    public static function classify(string $fileName): ?array
    {
        $a = self::ARCH;
        $patterns = [
            ['linux', 'deb', "/^atglance_.+_($a)\.deb$/"],
            ['linux', 'rpm', "/^atglance-.+\.($a)\.rpm$/"],
            ['linux', 'tar.gz', "/^atglance_.+_linux_($a)\.tar\.gz$/"],
            ['windows', 'zip', "/^atglance_.+_windows_($a)\.zip$/"],
            ['linux', 'binary', "/^atglance_.+_linux_($a)$/"],
            ['windows', 'binary', "/^atglance_.+_windows_($a)\.exe$/"],
            ['darwin', 'binary', "/^atglance_.+_darwin_($a)$/"],
        ];

        foreach ($patterns as [$os, $format, $regex]) {
            if (preg_match($regex, $fileName, $m)) {
                return ['os' => $os, 'arch' => self::ARCHES[$m[1]], 'format' => $format];
            }
        }

        return null;
    }

    /**
     * The newest CLI release and its files, or null when there is none.
     *
     * @return array{version: string, published_at: string, files: list<array<string, mixed>>}|null
     */
    public static function latest(): ?array
    {
        $rows = DownloadFile::where('product', 'cli')->whereNotNull('version')->get();

        $version = $rows->pluck('version')->unique()
            ->reduce(fn (?string $best, string $v) => $best === null || version_compare($v, $best, '>') ? $v : $best);

        if ($version === null) {
            return null;
        }

        $files = [];
        $published = null;
        foreach ($rows->where('version', $version) as $row) {
            $class = self::classify($row->file_name);
            if ($class === null) {
                continue;
            }
            $files[] = $class + [
                'name' => $row->file_name,
                'url' => $row->url(),
                'sha256' => $row->sha256,
                'size' => (int) $row->size,
            ];
            $published = $published === null || $row->created_at > $published ? $row->created_at : $published;
        }

        if ($files === []) {
            return null;
        }

        return ['version' => $version, 'published_at' => $published->toIso8601String(), 'files' => $files];
    }
}
