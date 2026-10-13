<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Binds one licence to the Management Console instance that activated it.
 */
class LicenseActivation extends Model
{
    protected $fillable = [
        'token_id', 'instance_id', 'org_name', 'hostname', 'console_version', 'ip_address', 'activated_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    public function toConsoleArray(): array
    {
        return [
            'instance_id' => $this->instance_id,
            'org_name' => $this->org_name,
            'hostname' => $this->hostname,
            'version' => $this->console_version,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
