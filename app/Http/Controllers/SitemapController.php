<?php

namespace App\Http\Controllers;

use App\Models\Doc;
use App\Models\Page;
use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt for the public SPA. URLs use FRONTEND_URL, so they match
 * the domain users visit. Docs and CMS pages are read from the database, so new ones
 * show up without a deploy.
 */
class SitemapController extends Controller
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

    public function sitemap(): Response
    {
        $base = rtrim(config('atglance.frontend_url'), '/');
        $urls = [];

        foreach (self::STATIC_PAGES as $path => [$freq, $priority]) {
            $urls[] = ['loc' => $base.($path === '/' ? '/' : $path), 'changefreq' => $freq, 'priority' => $priority, 'lastmod' => null];
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
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $base = rtrim(config('atglance.frontend_url'), '/');
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /api/',
            'Disallow: /dashboard',
            'Disallow: /reset-password',
            'Disallow: /auth/',
            '',
            "Sitemap: {$base}/sitemap.xml",
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
