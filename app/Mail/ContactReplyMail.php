<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * Admin reply to a /contact message, in the branded AtGlance email layout.
 * Sent from the "From address" in Admin → Settings → Email (SMTP); Reply-To is the support address.
 */
class ContactReplyMail extends Mailable
{
    public function __construct(public readonly ContactMessageReply $reply) {}

    public function envelope(): Envelope
    {
        $m = $this->reply->contactMessage;

        return new Envelope(
            subject: "Re: {$m->subject} {$m->reference()}",
            replyTo: [new Address(ContactMessage::recipient())],
        );
    }

    public function content(): Content
    {
        $disk = Storage::disk('public');

        return new Content(
            view: 'emails.contact-reply',
            text: 'emails.contact-reply-text',
            with: [
                'contact' => $this->reply->contactMessage,
                'body' => $this->reply->body,
                // Absolute paths so the template can embed them inline (cid:), which works without a public URL.
                'images' => collect($this->reply->images ?? [])
                    ->filter(fn (string $path) => $disk->exists($path))
                    ->map(fn (string $path) => $disk->path($path))
                    ->values()
                    ->all(),
            ],
        );
    }
}
