<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 5-digit one-time code a user enters to generate a Management Console licence file, when the
 * licence policy requires email verification. Sent synchronously (not queued) so it works
 * without a queue worker.
 */
class LicenseCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your AtGlance licence file code: {$this->code}")
            ->greeting('Confirm your licence file')
            ->line('Use this code on your AtGlance dashboard to generate a Management Console licence file:')
            ->line("**{$this->code}**")
            ->line("The code expires in {$this->minutes} minutes.")
            ->line('If you did not request this, you can ignore this email and consider changing your password.');
    }
}
