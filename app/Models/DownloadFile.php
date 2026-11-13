<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One downloadable file of a release (see App\Support\ReleaseFiles).
 *
 * @property string $product
 * @property string $folder
 * @property ?string $version
 * @property string $platform
 * @property ?string $arch
 * @property string $kind
 * @property string $file_name
 * @property string $path
 * @property int $size
 * @property string $sha256
 * @property ?string $legacy_path
 */
class DownloadFile extends Model
{
    public const PLATFORMS = ['linux' => 'Linux', 'windows' => 'Windows', 'macos' => 'macOS', 'any' => 'Any'];

    public const KINDS = ['package' => 'Package', 'installer' => 'Installer', 'updater' => 'Update script', 'script' => 'Script'];

    protected $guarded = ['id'];

    protected $casts = ['size' => 'integer', 'download_count' => 'integer'];

    /** Public download link on atglance.live. */
    public function url(): string
    {
        return route('downloads.file', [$this->product, $this->folder, $this->file_name]);
    }

    /** Old app.atglance.live link, or null. */
    public function legacyUrl(): ?string
    {
        $host = config('atglance.legacy_download_host');

        return $host && $this->legacy_path ? 'https://'.$host.'/'.$this->legacy_path : null;
    }

    /** "Debian package", "RPM package", "Archive", "Windows installer" ... from the file name. */
    public function typeLabel(): string
    {
        $n = strtolower($this->file_name);

        return match (true) {
            str_ends_with($n, '.deb') => 'Debian package',
            str_ends_with($n, '.rpm') => 'RPM package',
            str_ends_with($n, '.tar.gz'), str_ends_with($n, '.tgz') => 'Binary archive',
            str_ends_with($n, '.msi') => 'Windows installer (MSI)',
            str_ends_with($n, '.zip') => 'ZIP archive',
            str_ends_with($n, '.exe') => 'Windows executable',
            str_ends_with($n, '.pkg'), str_ends_with($n, '.dmg') => 'macOS package',
            str_ends_with($n, '.ps1') => 'PowerShell script',
            str_ends_with($n, '.sh') => 'Shell script',
            $this->platform === 'macos' && str_contains($n, 'darwin') => 'macOS binary',
            default => self::KINDS[$this->kind] ?? 'File',
        };
    }

    public function humanSize(): string
    {
        $b = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($b < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $b : number_format($b, 1)).' '.$unit;
            }
            $b /= 1024;
        }

        return $this->size.' B';
    }
}
