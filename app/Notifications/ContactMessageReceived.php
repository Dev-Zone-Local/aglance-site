<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emails a new /contact message to the support address. Reply-To is the sender. */
class ContactMessageReceived extends Notification
{
    public function __construct(public readonly ContactMessage $contact) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = $this->contact;
        $admin = rtrim(config('atglance.frontend_url'), '/').'/admin/messages/'.$m->getKey();

        $mail = (new MailMessage)
            ->subject("[{$m->topicLabel()}] {$m->subject} {$m->reference()}")
            ->replyTo($m->email, $m->name)
            ->greeting("New message {$m->reference()}")
            ->line("**From:** {$m->name} <{$m->email}>".($m->user_id ? ' (registered user)' : ''))
            ->line("**Topic:** {$m->topicLabel()}".($m->productLabel() ? " · **Product:** {$m->productLabel()}" : ''))
            ->line("**Subject:** {$m->subject}");

        foreach (preg_split("/\r\n|\n|\r/", trim($m->message)) as $line) {
            $mail->line($line === '' ? ' ' : $line);
        }

        return $mail
            ->action('Open in admin', $admin)
            ->line('Reply to this email to answer the sender directly.');
    }
}
