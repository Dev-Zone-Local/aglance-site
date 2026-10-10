<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "We got your message" email to the person who used the /contact form. */
class ContactMessageConfirmation extends Notification
{
    public function __construct(public readonly ContactMessage $contact) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->contact;

        return (new MailMessage)
            ->subject("We received your message {$m->reference()}")
            ->replyTo(ContactMessage::recipient())
            ->greeting("Hi {$m->name},")
            ->line("Thanks for contacting AtGlance. We received your message \"{$m->subject}\" and will get back to you by email.")
            ->line("Your reference is **{$m->reference()}**. Reply to this email to add more details.")
            ->action('Known problems and fixes', rtrim(config('atglance.frontend_url'), '/').'/known-problems');
    }
}
