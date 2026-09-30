<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A Management Console licence: a Sanctum token with the console:license ability,
 * at most one console activation, and (only for flagged users) an admin approval.
 *
 * A licence becomes usable once the owner enters the emailed 5-digit code. For users with
 * `license_requires_approval`, an admin must also approve it.
 *
 * Status: unverified (code not entered) → under_review (flagged users only) → ready → in_use.
 */
class License extends PersonalAccessToken
{
    public const ABILITY = 'console:license';

    public const UNVERIFIED = 'unverified';

    public const UNDER_REVIEW = 'under_review';

    public const READY = 'ready';

    public const IN_USE = 'in_use';

    public const STATUS_LABELS = [
        self::UNVERIFIED => 'Pending',
        self::UNDER_REVIEW => 'Under review',
        self::READY => 'Ready',
        self::IN_USE => 'In use',
    ];

    /** The owner can view the plain key for this long after creating the licence. */
    public const KEY_VISIBLE_MINUTES = 30;

    protected $table = 'personal_access_tokens';

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'plain_key' => 'encrypted',
        'key_visible_until' => 'datetime',
    ];

    protected $hidden = ['token', 'plain_key'];

    /**
     * Sanctum writes last_used_at on every authenticated request. Skip that write so a
     * read-only licence check changes nothing; console usage is tracked on the
     * activation (last_seen_at) instead.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists && array_keys($this->getDirty()) === ['last_used_at']) {
            $this->syncOriginalAttribute('last_used_at');

            return true;
        }

        return parent::save($options);
    }

    /** Keep the plain key (encrypted) so the owner can view it for KEY_VISIBLE_MINUTES. */
    public function rememberPlainKey(string $plainKey): void
    {
        $this->forceFill([
            'plain_key' => $plainKey,
            'key_visible_until' => now()->addMinutes(self::KEY_VISIBLE_MINUTES),
        ])->save();
    }

    /** The user entered the emailed code (proves they own the email address). */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function confirm(): void
    {
        $this->forceFill(['confirmed_at' => $this->confirmed_at ?? now()])->save();
    }

    /** The owner may view the key right now (within KEY_VISIBLE_MINUTES of creation). */
    public function keyIsVisible(): bool
    {
        return $this->key_visible_until?->isFuture() && $this->getRawOriginal('plain_key') !== null;
    }

    /** Remove stored plain keys whose viewing window has passed. */
    public static function forgetExpiredKeys(): int
    {
        return static::query()
            ->whereNotNull('plain_key')
            ->where('key_visible_until', '<=', now())
            ->update(['plain_key' => null, 'key_visible_until' => null]);
    }

    public function activation(): HasOne
    {
        return $this->hasOne(LicenseActivation::class, 'token_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Only licences of users flagged by an admin need approval. */
    public function requiresApproval(): bool
    {
        return (bool) $this->tokenable?->license_requires_approval;
    }

    /** Waiting for an admin (flagged user, not approved yet). */
    public function needsApproval(): bool
    {
        return $this->requiresApproval() && ! $this->isApproved();
    }

    /** A Management Console may activate / use this licence. */
    public function isUsable(): bool
    {
        return $this->isConfirmed() && ! $this->needsApproval();
    }

    public function status(): string
    {
        return match (true) {
            ! $this->isConfirmed() => self::UNVERIFIED,
            $this->needsApproval() => self::UNDER_REVIEW,
            $this->activation !== null => self::IN_USE,
            default => self::READY,
        };
    }

    public function approve(User $admin): void
    {
        if ($this->isApproved()) {
            return;
        }

        $this->forceFill(['approved_at' => now(), 'approved_by' => $admin->id])->save();
    }

    /** Only tokens that are Management Console licences. */
    public function scopeLicenses(Builder $query): Builder
    {
        return $query->whereJsonContains('abilities', self::ABILITY);
    }

    /** Licences of flagged users that an admin still has to approve. */
    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query
            ->whereNull('approved_at')
            ->whereHasMorph('tokenable', [User::class], fn (Builder $q) => $q->where('license_requires_approval', true));
    }
}
