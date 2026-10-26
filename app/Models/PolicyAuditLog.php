<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change an admin made to a licence policy or the feature catalog: who, what, old and
 * new value, when. Written by App\Support\LicensePolicy\PolicyAudit; never edited.
 */
class PolicyAuditLog extends Model
{
    protected $table = 'policy_audit_log';

    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'subject_type', 'subject_id', 'subject_label', 'action', 'field', 'old_value', 'new_value', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
