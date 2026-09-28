<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value settings store. Known keys: "contact", "downloads", "github", "mail".
 */
class Setting extends Model
{
    public const CONTACT = 'contact';

    public const DOWNLOADS = 'downloads';

    public const GITHUB = 'github';

    public const MAIL = 'mail';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, array $default = []): array
    {
        return static::firstWhere('key', $key)?->value ?? $default;
    }

    public static function put(string $key, array $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
