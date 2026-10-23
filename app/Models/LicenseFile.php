<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An offline licence file (.atgl) issued to a user (table `licenses`). Named LicenseFile
 * because License is the online licence key (a Sanctum token).
 */
class LicenseFile extends Model
{
    protected $table = 'licenses';

    protected $fillable = [
        'license_id', 'user_id', 'org', 'plan', 'seats', 'features',
        'issued_at', 'activate_before', 'expires_at', 'key_id', 'install_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'seats' => 'integer',
            'issued_at' => 'datetime',
            'activate_before' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
