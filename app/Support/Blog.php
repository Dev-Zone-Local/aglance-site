<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Setting;

/** Blog settings from Admin → Blog → Blog settings. */
class Blog
{
    public const DEFAULTS = [
        'enabled' => true,
        'title' => 'Blog',
        'intro' => 'Notes from the team that builds AtGlance: releases, how-tos and how we run servers.',
        'per_page' => 9,
        'show_in_nav' => true,
        'rss' => true,
    ];

    public static function settings(): array
    {
        $saved = Setting::get(Setting::BLOG);

        return array_replace(self::DEFAULTS, array_filter($saved, fn ($v) => $v !== null && $v !== ''));
    }

    public static function enabled(): bool
    {
        return (bool) self::settings()['enabled'];
    }

    /** The Blog link shows in the top navigation: blog on, link on, and at least one public post. */
    public static function inNav(): bool
    {
        return self::enabled() && self::settings()['show_in_nav'] && BlogPost::published()->exists();
    }
}
