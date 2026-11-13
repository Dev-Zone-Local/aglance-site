<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Doc;
use App\Models\Page;
use Illuminate\Support\Carbon;

/**
 * sitemap.xml and robots.txt, served by the /sitemap.xml and /robots.txt routes. Docs and CMS
 * pages come from the database, so new ones show up without a deploy. URLs use FRONTEND_URL
 * (the public site address).
 */
class Sitemap
{
    /** Marketing pages: path => [changefreq, priority]. */
    private const STATIC_PAGES = [
        '/' => ['weekly', '1.0'],
        '/product' => ['monthly', '0.9'],
        '/cli' => ['monthly', '0.9'],
        '/console' => ['monthly', '0.9'],
        '/architecture' => ['monthly', '0.7'],
        '/use-cases' => ['monthly', '0.7'],
        '/security' => ['monthly', '0.7'],
        '/features/remote-tunnel' => ['monthly', '0.8'],
        '/pricing' => ['monthly', '0.8'],
        '/faq' => ['monthly', '0.6'],
        '/contact' => ['yearly', '0.5'],
        '/docs' => ['weekly', '0.8'],
        '/downloads' => ['weekly', '0.9'],
        '/releases' => ['weekly', '0.7'],
        '/roadmap' => ['monthly', '0.6'],
        '/known-problems' => ['weekly', '0.6'],
        '/register' => ['yearly', '0.4'],
    ];

    /** CMS pages that have a public route. */
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

        if (Blog::enabled()) {
            $urls[] = ['loc' => "{$base}/blog", 'changefreq' => 'weekly', 'priority' => '0.7', 'lastmod' => null];
            foreach (BlogPost::published()->latest('published_at')->get(['slug', 'updated_at']) as $post) {
                $urls[] = ['loc' => "{$base}/blog/".rawurlencode($post->slug), 'changefreq' => 'monthly', 'priority' => '0.6', 'lastmod' => $post->updated_at];
            }
        }

        foreach (Downloads::changelog() as $r) {
            $urls[] = [
                'loc' => "{$base}/releases/{$r['product']}/".rawurlencode($r['version']),
                'changefreq' => 'yearly',
                'priority' => $r['latest'] ? '0.7' : '0.5',
                'lastmod' => $r['released_at'] ? Carbon::parse($r['released_at']) : null,
            ];
        }

        // Pages marked "do not index" under Admin → SEO stay out of the sitemap.
        $skip = array_map(fn ($p) => $base.($p === '/' ? '/' : $p), Seo::noindexPaths());
        $urls = array_filter($urls, fn ($u) => ! in_array($u['loc'], $skip, true));

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
        // Fixed paths plus the admin's (Admin → SEO); "Disallow: /" when indexing is turned off.
        return implode("\n", [
            'User-agent: *',
            ...array_map(fn ($p) => "Disallow: {$p}", Seo::disallowed()),
            '',
            'Sitemap: '.self::base().'/sitemap.xml',
            '',
        ]);
    }

    private static function base(): string
    {
        return rtrim(config('atglance.frontend_url'), '/');
    }
}
