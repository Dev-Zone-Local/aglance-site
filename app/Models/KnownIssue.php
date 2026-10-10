<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A known problem and its solution (Markdown). Tagged by product (CLI or Management Console),
 * optional platforms and free tags. Managed under Admin → Content → Known problems.
 */
class KnownIssue extends Model
{
    public const PRODUCTS = ['console' => 'Management Console', 'cli' => 'AtGlance CLI'];

    public const PLATFORMS = ['linux' => 'Linux', 'windows' => 'Windows', 'raspberry-pi' => 'Raspberry Pi', 'aws' => 'AWS', 'azure' => 'Azure', 'gcp' => 'Google Cloud'];

    protected $fillable = ['title', 'slug', 'product', 'platforms', 'tags', 'symptom', 'solution', 'order', 'is_published'];

    protected function casts(): array
    {
        return ['platforms' => 'array', 'tags' => 'array', 'order' => 'integer', 'is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (KnownIssue $issue) {
            if (blank($issue->slug)) {
                $base = Str::slug($issue->title) ?: 'problem';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->whereKeyNot($issue->getKey())->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $issue->slug = $slug;
            }
        });
    }

    /** @param  Builder<KnownIssue>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->orderBy('order')->orderBy('id');
    }

    public function productLabel(): string
    {
        return self::PRODUCTS[$this->product] ?? $this->product;
    }

    /** @return list<string> */
    public function platformLabels(): array
    {
        return array_map(fn ($p) => self::PLATFORMS[$p] ?? $p, $this->platforms ?? []);
    }
}
