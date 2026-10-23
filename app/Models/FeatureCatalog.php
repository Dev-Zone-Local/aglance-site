<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Features a licence can enable. Keys come from the shared contract (FEATURES.md, synced to
 * resources/licensing/features.json) and are never created, renamed or removed here; admins
 * may edit the display name and description.
 */
class FeatureCatalog extends Model
{
    protected $table = 'feature_catalog';

    protected $fillable = ['key', 'name', 'description', 'default_enabled', 'sort'];

    protected function casts(): array
    {
        return ['default_enabled' => 'boolean', 'sort' => 'integer'];
    }

    /** @return array<string, string> key => display name, in catalog order */
    public static function options(): array
    {
        return static::query()->orderBy('sort')->pluck('name', 'key')->all();
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return static::query()->orderBy('sort')->pluck('key')->all();
    }

    /** @return list<string> keys enabled in new default policies */
    public static function defaultKeys(): array
    {
        return static::query()->where('default_enabled', true)->orderBy('sort')->pluck('key')->all();
    }
}
