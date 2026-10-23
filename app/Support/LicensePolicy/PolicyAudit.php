<?php

namespace App\Support\LicensePolicy;

use App\Models\PolicyAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes policy_audit_log rows: one per changed field, with the old and new value.
 */
class PolicyAudit
{
    /** Fields recorded for policies and catalog entries. */
    public const FIELDS = [
        'inherit_global', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'expires_on',
        'activation_window_minutes', 'max_files', 'require_email_code', 'install_id_mode', 'features', 'install_id', 'notes', 'name', 'description',
    ];

    /**
     * @param  array<string, mixed>  $old  values before the change ([] when created)
     * @param  array<string, mixed>  $new  values after the change
     */
    public static function record(?int $adminId, string $type, Model $subject, string $label, string $action, array $old, array $new): int
    {
        $count = 0;
        $now = now();
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $new) && ! array_key_exists($field, $old)) {
                continue;
            }
            $before = self::text($old[$field] ?? null);
            $after = self::text($new[$field] ?? null);
            if ($action === 'updated' && $before === $after) {
                continue;
            }
            PolicyAuditLog::create([
                'admin_id' => $adminId, 'subject_type' => $type, 'subject_id' => $subject->getKey(), 'subject_label' => $label,
                'action' => $action, 'field' => $field, 'old_value' => $before, 'new_value' => $after, 'created_at' => $now,
            ]);
            $count++;
        }

        if ($count === 0 && $action !== 'updated') {
            PolicyAuditLog::create([
                'admin_id' => $adminId, 'subject_type' => $type, 'subject_id' => $subject->getKey(), 'subject_label' => $label,
                'action' => $action, 'created_at' => $now,
            ]);
            $count = 1;
        }

        return $count;
    }

    private static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null || $value === '' => null,
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => implode(', ', array_map('strval', $value)),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => (string) $value,
        };
    }
}
