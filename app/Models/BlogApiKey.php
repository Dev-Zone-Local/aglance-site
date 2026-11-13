<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A key for the Blog API. The plain key (agb_...) is shown once when created; only its SHA-256
 * is stored. Posts created with a key are written as the admin who created the key.
 */
class BlogApiKey extends Model
{
    public const PREFIX = 'agb_';

    protected $fillable = ['name', 'token_hash', 'last_four', 'created_by', 'last_used_at', 'last_used_ip'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /** @return array{0: BlogApiKey, 1: string} the key and its plain value (show it once) */
    public static function issue(string $name, ?User $by = null): array
    {
        $plain = self::PREFIX.Str::random(40);
        $key = self::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'last_four' => substr($plain, -4),
            'created_by' => $by?->id,
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(?string $plain): ?self
    {
        if (! $plain || ! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        return self::firstWhere('token_hash', hash('sha256', $plain));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function masked(): string
    {
        return self::PREFIX.'••••'.$this->last_four;
    }
}
