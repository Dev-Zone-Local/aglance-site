<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the user an admin approved their licence, so the Management Console can now use it.
 */
class LicenseApprovedNotification extends Notification
{
    public function __construct(public readonly string $licenseName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AtGlance licence is approved')
            ->greeting('Licence approved')
            ->line("Your Management Console licence \"{$this->licenseName}\" has been approved.")
            ->line('Enter the licence key in the Management Console installer to activate it.')
            ->action('Open dashboard', rtrim(config('atglance.frontend_url'), '/').'/dashboard');
    }
}
