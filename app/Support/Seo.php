<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Search and sharing settings from Admin → SEO, applied to every public page (base layout),
 * robots.txt and the sitemap. A page override (by path) wins over the page's own title and
 * description, which win over the defaults.
 */
class Seo
{
    public const IMAGE_DIR = 'seo';

    public const DEFAULTS = [
        'site_name' => 'AtGlance',
        'title_separator' => ' · ',
        'home_title' => 'AtGlance — Operations at a glance, inside your boundary',
        'default_description' => 'AtGlance is a self-hosted operations platform for SREs: a CLI for every Linux server and a Management Console you host.',
        'og_image' => null,
        'twitter_handle' => null,
        'allow_indexing' => true,
        'robots_disallow' => null,
        'google_verification' => null,
        'bing_verification' => null,
        'pages' => [],
    ];

    /** Paths that are never indexed, whatever the settings say. */
    public const ALWAYS_DISALLOWED = ['/admin', '/api/', '/dashboard', '/reset-password', '/auth/'];

    public static function settings(): array
    {
        $saved = Setting::get(Setting::SEO);

        return array_replace(self::DEFAULTS, array_filter($saved, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Everything the <head> needs for one request.
     *
     * @return array{title: string, description: string, image: string, noindex: bool, canonical: string, twitter: ?string, site_name: string, google: ?string, bing: ?string}
     */
    public static function meta(?string $title, ?string $description, string $path, ?string $image = null): array
    {
        $s = self::settings();
        $path = '/'.trim($path, '/');
        $override = collect($s['pages'] ?? [])->first(fn ($p) => '/'.trim((string) ($p['path'] ?? ''), '/') === $path) ?? [];
        $base = rtrim(config('atglance.frontend_url'), '/');

        $title = filled($override['title'] ?? null) ? $override['title']
            : ($title ? $title.$s['title_separator'].$s['site_name'] : $s['home_title']);
        $image = $image ?: (filled($override['image'] ?? null) ? $override['image'] : $s['og_image']);

        return [
            'title' => $title,
            'description' => filled($override['description'] ?? null) ? $override['description'] : ($description ?: $s['default_description']),
            'image' => $image ? (str_starts_with($image, 'http') ? $image : asset('storage/'.ltrim($image, '/'))) : $base.'/branding/atglance-logo.png',
            'noindex' => ! $s['allow_indexing'] || ! empty($override['noindex']),
            'canonical' => $base.($path === '/' ? '' : $path),
            'twitter' => $s['twitter_handle'] ? '@'.ltrim($s['twitter_handle'], '@') : null,
            'site_name' => $s['site_name'],
            'google' => $s['google_verification'],
            'bing' => $s['bing_verification'],
        ];
    }

    /** Disallow lines for robots.txt (fixed ones plus the admin's). Everything when indexing is off. */
    public static function disallowed(): array
    {
        $s = self::settings();
        if (! $s['allow_indexing']) {
            return ['/'];
        }
        $extra = preg_split('/\R/', (string) $s['robots_disallow']) ?: [];
        $extra = array_map(fn ($l) => '/'.ltrim(trim($l), '/'), array_filter($extra, fn ($l) => trim($l) !== ''));

        return array_values(array_unique([...self::ALWAYS_DISALLOWED, ...$extra]));
    }

    /** Paths with a "do not index" override, left out of the sitemap. */
    public static function noindexPaths(): array
    {
        return collect(self::settings()['pages'] ?? [])->filter(fn ($p) => ! empty($p['noindex']))
            ->map(fn ($p) => '/'.trim((string) $p['path'], '/'))->values()->all();
    }
}
