<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent with the form on /contact. Saved first, then emailed to the support address
 * (Admin → Settings → Contact → "Form messages go to"). Admins work through them under
 * Admin → Support → Messages.
 */
class ContactMessage extends Model
{
    public const TOPICS = [
        'support' => 'Problem / support',
        'bug' => 'Bug report',
        'sales' => 'Sales / pricing',
        'licence' => 'Licence',
        'security' => 'Security report',
        'feedback' => 'Feedback / idea',
        'other' => 'Something else',
    ];

    public const PRODUCTS = ['console' => 'Management Console', 'cli' => 'AtGlance CLI', 'website' => 'Website / account'];

    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'spam' => 'Spam'];

    /** Where form messages go when no address is set in Contact settings. */
    public const DEFAULT_RECIPIENT = 'support@atglance.live';

    protected $fillable = ['user_id', 'name', 'email', 'topic', 'product', 'subject', 'message', 'status', 'admin_notes', 'ip', 'user_agent', 'emailed_at'];

    protected function casts(): array
    {
        return ['emailed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Short reference shown to the sender and in email subjects, e.g. "#00042". */
    public function reference(): string
    {
        return '#'.str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? $this->topic;
    }

    public function productLabel(): ?string
    {
        return $this->product ? (self::PRODUCTS[$this->product] ?? $this->product) : null;
    }

    public static function recipient(): string
    {
        $to = Setting::get(Setting::CONTACT)['form_recipient'] ?? null;

        return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : self::DEFAULT_RECIPIENT;
    }
}
