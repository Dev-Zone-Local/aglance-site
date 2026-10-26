<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An email reply an admin sent to a contact message. Images are stored on the "public" disk
 * under IMAGE_DIR and embedded inline in the email.
 */
class ContactMessageReply extends Model
{
    public const IMAGE_DIR = 'contact-replies';

    protected $fillable = ['contact_message_id', 'user_id', 'body', 'images', 'sent_at', 'error'];

    protected function casts(): array
    {
        return ['images' => 'array', 'sent_at' => 'datetime'];
    }

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
