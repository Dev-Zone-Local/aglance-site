<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * CLI and Management Console downloads, stored in the "downloads" settings row:
 *
 *   { "cli":     { "url", "file_name", "install_steps",
 *                  "releases": [ {version, checksum, title, type, summary, notes, released_at}, ... ] },
 *     "console": { ... } }
 *
 * The console also has "platforms": { "linux": {install_steps, update_steps, script_url}, "windows": {...} }.
 *
 * releases[0] is the latest. Publishing a release puts it first and keeps only MAX_RELEASES.
 * Checksums are SHA-256. "notes" is Markdown (the full release notes shown on /releases);
 * "summary" is one or two plain sentences for emails and cards.
 */
class Downloads
{
    public const PRODUCTS = ['cli' => 'AtGlance CLI', 'console' => 'Management Console'];

    public const MAX_RELEASES = 20;

    /** Release types shown as a badge next to the version. */
    public const RELEASE_TYPES = [
        'major' => 'Major',
        'minor' => 'Feature',
        'patch' => 'Bug fix',
        'security' => 'Security',
        'beta' => 'Beta',
    ];

    /** Starting text for the release notes editor. */
    public const NOTES_TEMPLATE = "### New\n- \n\n### Improved\n- \n\n### Fixed\n- \n";

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
    public static function publish(string $product, string $version, ?string $checksum = null, ?string $notes = null, array $details = []): array
    {
        $all = self::all();
        $releases = array_values(array_filter(
            $all[$product]['releases'],
            fn (array $r) => $r['version'] !== $version,
        ));

        array_unshift($releases, [
            'version' => $version,
            'checksum' => $checksum && self::hasChecksum($product) ? strtolower($checksum) : null,
            ...self::details($details),
            'notes' => self::cleanNotes($notes),
            'released_at' => self::date($details['released_at'] ?? null) ?? now()->toIso8601String(),
        ]);

        $all[$product]['releases'] = array_slice($releases, 0, self::MAX_RELEASES);
        self::save($all);

        return $all[$product];
    }

    /** Edit one release in the history (index 0 = latest). Keeps its position and date. */
    public static function updateRelease(string $product, int $index, string $version, ?string $checksum, ?string $notes, array $details = []): void
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
            ...self::details($details),
            'notes' => self::cleanNotes($notes),
            'released_at' => self::date($details['released_at'] ?? null) ?? ($all[$product]['releases'][$index]['released_at'] ?? null),
        ];
        self::save($all);
    }

    /** Every published release of every product, newest first, for the public changelog. */
    public static function changelog(?string $product = null): array
    {
        $out = [];
        foreach (self::all() as $p => $data) {
            if ($product && $p !== $product) {
                continue;
            }
            foreach ($data['releases'] as $i => $r) {
                $out[] = [...$r, 'product' => $p, 'product_name' => self::PRODUCTS[$p], 'latest' => $i === 0];
            }
        }
        usort($out, fn ($a, $b) => strcmp((string) ($b['released_at'] ?? ''), (string) ($a['released_at'] ?? '')));

        return $out;
    }

    /** Anchor id of a release on the /releases page, e.g. "cli-2.1.0". */
    public static function anchor(string $product, string $version): string
    {
        return $product.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $version);
    }

    /** Optional release fields: title, type, summary. */
    private static function details(array $details): array
    {
        $type = $details['type'] ?? null;

        return [
            'title' => filled($details['title'] ?? null) ? trim($details['title']) : null,
            'type' => isset(self::RELEASE_TYPES[$type]) ? $type : null,
            'summary' => filled($details['summary'] ?? null) ? trim($details['summary']) : null,
        ];
    }

    /** Empty notes, or the untouched editor template, count as no notes. */
    private static function cleanNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);
        if ($notes === '' || preg_replace('/\s+/', '', $notes) === preg_replace('/\s+/', '', self::NOTES_TEMPLATE)) {
            return null;
        }

        return $notes;
    }

    private static function date(mixed $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toIso8601String() : null;
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
                'releases' => array_map(
                    fn (array $r) => $r + ['title' => null, 'type' => null, 'summary' => null, 'notes' => null, 'released_at' => null],
                    array_values($current['releases'] ?? []),
                ),
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
                'title' => null,
                'type' => null,
                'summary' => null,
                'notes' => null,
                'released_at' => null,
            ]] : [],
        ];
    }
}
