<?php

namespace App\Support;

use App\Models\Doc;
use App\Models\Page;

/**
 * sitemap.xml and robots.txt for the public SPA. URLs use FRONTEND_URL, so they match the
 * domain users visit. Served by the /sitemap.xml and /robots.txt routes, and also written to
 * public/ as static files (write()), so nginx can serve them even when its SPA fallback would
 * otherwise answer those paths with index.html.
 */
class Sitemap
{
    /** Marketing pages of the SPA: path => [changefreq, priority]. */
    private const STATIC_PAGES = [
        '/' => ['weekly', '1.0'],
        '/product' => ['monthly', '0.9'],
        '/cli' => ['monthly', '0.9'],
        '/console' => ['monthly', '0.9'],
        '/architecture' => ['monthly', '0.7'],
        '/use-cases' => ['monthly', '0.7'],
        '/security' => ['monthly', '0.7'],
        '/pricing' => ['monthly', '0.8'],
        '/faq' => ['monthly', '0.6'],
        '/contact' => ['yearly', '0.5'],
        '/docs' => ['weekly', '0.8'],
        '/register' => ['yearly', '0.4'],
    ];

    /** CMS pages that have a public route in the SPA. */
    private const CMS_PAGES = ['about', 'terms', 'privacy'];

    public static function xml(): string
    {
        $base = self::base();
        $urls = [];

        foreach (self::STATIC_PAGES as $path => [$freq, $priority]) {
            $urls[] = ['loc' => $base.$path, 'changefreq' => $freq, 'priority' => $priority, 'lastmod' => null];
        }

        foreach (Page::whereIn('slug', self::CMS_PAGES)->get(['slug', 'updated_at']) as $page) {
            $urls[] = ['loc' => "{$base}/{$page->slug}", 'changefreq' => 'yearly', 'priority' => '0.3', 'lastmod' => $page->updated_at];
        }

        foreach (Doc::orderBy('order')->get(['slug', 'updated_at']) as $doc) {
            $urls[] = ['loc' => "{$base}/docs/".rawurlencode($doc->slug), 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $doc->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url>'
                .'<loc>'.e($u['loc']).'</loc>'
                .($u['lastmod'] ? '<lastmod>'.$u['lastmod']->toDateString().'</lastmod>' : '')
                .'<changefreq>'.$u['changefreq'].'</changefreq>'
                .'<priority>'.$u['priority'].'</priority>'
                ."</url>\n";
        }

        return $xml."</urlset>\n";
    }

    public static function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /dashboard',
            'Disallow: /reset-password',
            'Disallow: /auth/',
            '',
            'Sitemap: '.self::base().'/sitemap.xml',
            '',
        ]);
    }

    /**
     * Write public/sitemap.xml and public/robots.txt. Skipped in tests so the suite
     * never touches the real public folder.
     *
     * @return array<int, string> the files written
     */
    public static function write(?string $dir = null): array
    {
        if ($dir === null && app()->runningUnitTests()) {
            return [];
        }

        $dir = rtrim($dir ?? public_path(), '/\\');
        $files = ["{$dir}/sitemap.xml" => self::xml(), "{$dir}/robots.txt" => self::robots()];

        foreach ($files as $path => $content) {
            // Write then rename, so nginx never serves a half-written file.
            file_put_contents("{$path}.tmp", $content);
            rename("{$path}.tmp", $path);
        }

        return array_keys($files);
    }

    /** write() for model hooks: a file error is logged, never breaks the admin save. */
    public static function refresh(): void
    {
        try {
            self::write();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function base(): string
    {
        return rtrim(config('atglance.frontend_url'), '/');
    }
}
