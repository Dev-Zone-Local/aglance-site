<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Management Console licence file (.atgl) issued to a user (table `licenses`). A file counts
 * toward the user's licence file limit until it expires, unless an admin released it.
 */
class LicenseFile extends Model
{
    protected $table = 'licenses';

    protected $fillable = [
        'format_version', 'license_id', 'user_id', 'org', 'plan', 'seats', 'max_nodes', 'max_users', 'features',
        'policy_id', 'policy_type', 'validity_months', 'issued_by',
        'issued_at', 'activate_before', 'expires_at', 'key_id', 'install_id', 'released_at', 'released_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'format_version' => 'integer',
            'seats' => 'integer',
            'max_nodes' => 'integer',
            'max_users' => 'integer',
            'validity_months' => 'integer',
            'issued_at' => 'datetime',
            'activate_before' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Counts toward the limit: not expired and not released by an admin. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now())->whereNull('released_at');
    }

    public function isActive(): bool
    {
        return ! $this->isExpired() && $this->released_at === null;
    }

    /** "Active", "Expired" or "Released". */
    public function statusLabel(): string
    {
        return match (true) {
            $this->released_at !== null => 'Released',
            $this->isExpired() => 'Expired',
            default => 'Active',
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Minutes left to install the file in a console (0 once the window has passed). */
    public function activationMinutesLeft(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->activate_before, false) / 60));
    }

    /** "activation window: expired" or "activation window: 42 min left". */
    public function activationWindowLabel(): string
    {
        $left = $this->activationMinutesLeft();

        return 'activation window: '.($left === 0 ? 'expired' : $left.' min left');
    }
}
