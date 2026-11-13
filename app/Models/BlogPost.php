<?php

namespace App\Models;

use App\Support\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A blog post. Public on /blog/{slug} when status is "published" and published_at has passed
 * (a future date schedules the post).
 */
class BlogPost extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published'];

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'cover_image', 'tags', 'status', 'published_at', 'author_id', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (BlogPost $post) {
            $post->slug = Str::slug($post->slug ?: $post->title) ?: Str::random(8);
            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast();
    }

    public function url(): string
    {
        return route('blog.show', $this->slug);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? asset('storage/'.ltrim($this->cover_image, '/')) : null;
    }

    /** Excerpt, or the start of the body as plain text. */
    public function summary(int $length = 180): string
    {
        return $this->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(Markdown::toHtml((string) $this->body)))), $length);
    }

    /** About 220 words a minute, at least 1. */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 220));
    }
}
