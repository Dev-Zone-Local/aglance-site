<?php

namespace App\Support;

use App\Models\Setting;

/**
 * CLI and Management Console downloads, stored in the "downloads" settings row:
 *
 *   { "cli":     { "url", "file_name", "install_steps", "releases": [ {version, checksum, notes, released_at}, ... ] },
 *     "console": { ... } }
 *
 * The console also has "platforms": { "linux": {install_steps, update_steps, script_url}, "windows": {...} }.
 *
 * releases[0] is the latest. Publishing a release puts it first and keeps only MAX_RELEASES.
 * Checksums are SHA-256.
 */
class Downloads
{
    public const PRODUCTS = ['cli' => 'AtGlance CLI', 'console' => 'Management Console'];

    public const MAX_RELEASES = 5;

    public const CHECKSUM_RULE = '/^[a-f0-9]{64}$/';

    /** Platforms each product supports; each gets its own Install and Update instructions. */
    public const PRODUCT_PLATFORMS = ['cli' => ['linux'], 'console' => ['linux', 'windows']];

    /** @return array<string, string> os key => label */
    public static function platformsFor(string $product): array
    {
        return array_intersect_key(self::PLATFORMS, array_flip(self::PRODUCT_PLATFORMS[$product] ?? []));
    }

    /** Products that publish a SHA-256 checksum (the console ships Docker images, so no file checksum). */
    public const CHECKSUM_PRODUCTS = ['cli'];

    public static function hasChecksum(string $product): bool
    {
        return in_array($product, self::CHECKSUM_PRODUCTS, true);
    }

    public const PLATFORMS = ['linux' => 'Linux', 'windows' => 'Windows'];

    public static function all(): array
    {
        $saved = Setting::get(Setting::DOWNLOADS);
        $out = [];

        foreach (array_keys(self::PRODUCTS) as $p) {
            $out[$p] = self::normalize($p, $saved[$p] ?? null, $saved);
        }

        return $out;
    }

    public static function save(array $products): void
    {
        Setting::put(Setting::DOWNLOADS, $products);
    }

    /**
     * Put a new release first; keep MAX_RELEASES. Re-publishing an existing version replaces it.
     *
     * @return array the updated product
     */
    public static function publish(string $product, string $version, ?string $checksum = null, ?string $notes = null): array
    {
        $all = self::all();
        $releases = array_values(array_filter(
            $all[$product]['releases'],
            fn (array $r) => $r['version'] !== $version,
        ));

        array_unshift($releases, [
            'version' => $version,
            'checksum' => $checksum && self::hasChecksum($product) ? strtolower($checksum) : null,
            'notes' => $notes ?: null,
            'released_at' => now()->toIso8601String(),
        ]);

        $all[$product]['releases'] = array_slice($releases, 0, self::MAX_RELEASES);
        self::save($all);

        return $all[$product];
    }

    /** Edit one release in the history (index 0 = latest). Keeps its position and date. */
    public static function updateRelease(string $product, int $index, string $version, ?string $checksum, ?string $notes): void
    {
        $all = self::all();
        abort_unless(isset($all[$product]['releases'][$index]), 404, 'Release not found');

        foreach ($all[$product]['releases'] as $i => $r) {
            abort_if($i !== $index && $r['version'] === $version, 422, "Version {$version} already exists.");
        }

        $all[$product]['releases'][$index] = [
            ...$all[$product]['releases'][$index],
            'version' => $version,
            'checksum' => $checksum && self::hasChecksum($product) ? strtolower($checksum) : null,
            'notes' => $notes ?: null,
        ];
        self::save($all);
    }

    public static function deleteRelease(string $product, int $index): void
    {
        $all = self::all();
        abort_unless(isset($all[$product]['releases'][$index]), 404, 'Release not found');

        array_splice($all[$product]['releases'], $index, 1);
        self::save($all);
    }

    /** Shell command that checks a downloaded file against a release checksum. */
    public static function validateCommand(array $product, ?array $release = null): ?string
    {
        $release ??= $product['releases'][0] ?? null;
        if (! $release || ! $release['checksum']) {
            return null;
        }

        $file = $product['file_name'] ?: 'FILE';

        return "echo \"{$release['checksum']}  {$file}\" | sha256sum -c -";
    }

    /** PowerShell equivalent: prints True when the file matches. */
    public static function validateCommandWindows(array $product, ?array $release = null): ?string
    {
        $release ??= $product['releases'][0] ?? null;
        if (! $release || ! $release['checksum']) {
            return null;
        }

        $file = $product['file_name'] ?: 'FILE';

        return '(Get-FileHash -Algorithm SHA256 .\\'.$file.').Hash -eq "'.strtoupper($release['checksum']).'"';
    }

    /** JSON for the dashboard (/api/downloads), including the legacy flat keys. */
    public static function payload(): array
    {
        $all = self::all();
        $out = [];

        foreach ($all as $p => $product) {
            $latest = $product['releases'][0] ?? null;

            $out[$p] = [
                'name' => self::PRODUCTS[$p],
                'url' => $product['url'],
                'file_name' => $product['file_name'],
                'install_steps' => $product['install_steps'],
                'has_checksum' => self::hasChecksum($p),
                'latest' => $latest ? self::presentRelease($p, $product, $latest) : null,
                'previous' => array_map(fn ($r) => self::presentRelease($p, $product, $r), array_slice($product['releases'], 1)),
            ];
            if (isset($product['platforms'])) {
                $out[$p]['platforms'] = $product['platforms'];
            }

            // Legacy keys used by older frontends.
            $out["{$p}_url"] = $product['url'];
            $out["{$p}_version"] = $latest['version'] ?? null;
            $out["{$p}_checksum"] = self::hasChecksum($p) ? ($latest['checksum'] ?? null) : null;
        }

        return $out;
    }

    private static function presentRelease(string $p, array $product, array $r): array
    {
        if (! self::hasChecksum($p)) {
            return [...$r, 'checksum' => null, 'validate_command' => null, 'validate_command_windows' => null];
        }

        return [
            ...$r,
            'validate_command' => self::validateCommand($product, $r),
            'validate_command_windows' => self::validateCommandWindows($product, $r),
        ];
    }

    /** Accepts the current shape or the old flat keys (cli_url, cli_version, cli_checksum, ...). */
    private static function normalize(string $p, ?array $current, array $legacy): array
    {
        $product = self::normalizeBase($p, $current, $legacy);

        $saved = $current['platforms'] ?? [];
        foreach (array_keys(self::platformsFor($p)) as $os) {
            $product['platforms'][$os] = [
                // Older single install_steps become the Linux steps.
                'install_steps' => $saved[$os]['install_steps'] ?? ($os === 'linux' ? $product['install_steps'] : null),
                'update_steps' => $saved[$os]['update_steps'] ?? null,
                'script_url' => $saved[$os]['script_url'] ?? null,
                // Download link + file name differ per platform; Linux falls back to the old single values.
                'url' => $saved[$os]['url'] ?? ($os === 'linux' ? $product['url'] : null),
                'file_name' => $saved[$os]['file_name'] ?? ($os === 'linux' ? $product['file_name'] : null),
            ];
        }

        return $product;
    }

    private static function normalizeBase(string $p, ?array $current, array $legacy): array
    {
        if (is_array($current)) {
            return [
                'url' => $current['url'] ?? null,
                'file_name' => $current['file_name'] ?? null,
                'install_steps' => $current['install_steps'] ?? null,
                'releases' => array_values($current['releases'] ?? []),
            ];
        }

        $version = $legacy["{$p}_version"] ?? null;

        return [
            'url' => $legacy["{$p}_url"] ?? null,
            'file_name' => null,
            'install_steps' => $p === 'cli' && ! empty($legacy['cli_install_command'])
                ? "```bash\n{$legacy['cli_install_command']}\n```"
                : null,
            'releases' => $version ? [[
                'version' => $version,
                'checksum' => ($legacy["{$p}_checksum"] ?? null) ?: null,
                'notes' => null,
                'released_at' => null,
            ]] : [],
        ];
    }
}
