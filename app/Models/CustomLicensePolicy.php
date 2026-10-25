<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A licence policy an admin set for one user. While it is not revoked, the user's generated
 * licence files use it (policy_type "custom"). Revoking makes new files use the default
 * policy again; files already downloaded keep working until they expire.
 */
class CustomLicensePolicy extends Model
{
    protected $fillable = [
        'user_id', 'created_by_admin_id', 'updated_by_admin_id', 'max_nodes', 'max_users',
        'validity_value', 'validity_unit', 'expires_on', 'activation_window_minutes', 'max_licences', 'install_id_mode', 'features',
        'install_id', 'notes', 'revoked_at', 'revoked_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'max_nodes' => 'integer',
            'max_users' => 'integer',
            'validity_value' => 'integer',
            'activation_window_minutes' => 'integer',
            'max_licences' => 'integer',
            'features' => 'array',
            'expires_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function policyId(): string
    {
        return 'custom:'.$this->id;
    }
}
