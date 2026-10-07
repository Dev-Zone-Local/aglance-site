<?php

namespace App\Http\Controllers;

use App\Support\Sitemap;
use Illuminate\Http\Response;

/** Dynamic /sitemap.xml and /robots.txt (see App\Support\Sitemap). */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        return response(Sitemap::xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        return response(Sitemap::robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
