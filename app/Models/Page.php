<?php

namespace App\Models;

use App\Support\Sitemap;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    /** Keep the static public/sitemap.xml current when content changes in the admin. */
    protected static function booted(): void
    {
        static::saved(fn () => Sitemap::refresh());
        static::deleted(fn () => Sitemap::refresh());
    }

    protected $fillable = ['slug', 'title', 'content'];

    protected $hidden = ['id', 'created_at', 'updated_at'];
}
