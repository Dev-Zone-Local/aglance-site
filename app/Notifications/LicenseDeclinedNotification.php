<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the user an admin declined their licence request (the licence is removed).
 */
class LicenseDeclinedNotification extends Notification
{
    public function __construct(
        public readonly string $licenseName,
        public readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your AtGlance licence request was declined')
            ->greeting('Licence request declined')
            ->line("Your Management Console licence request \"{$this->licenseName}\" was declined.");

        if ($this->reason) {
            $mail->line("Reason: {$this->reason}");
        }

        return $mail
            ->line('You can create a new licence request from your dashboard, or reply to this email if you have questions.')
            ->action('Open dashboard', rtrim(config('atglance.frontend_url'), '/').'/dashboard');
    }
}
