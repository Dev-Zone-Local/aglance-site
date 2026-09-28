<?php

namespace App\Notifications;

use App\Support\LicenseCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 5-digit one-time code a user must enter to create or revoke a Management Console licence.
 * Sent synchronously (not queued) so it works without a queue worker.
 */
class LicenseCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $licenseName,
        public readonly int $minutes,
        public readonly string $purpose = LicenseCode::CREATE,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $revoke = $this->purpose === LicenseCode::REVOKE;

        return (new MailMessage)
            ->subject("Your AtGlance licence code: {$this->code}")
            ->greeting($revoke ? 'Confirm licence revocation' : 'Confirm your new licence')
            ->line($revoke
                ? "Use this code to revoke the Management Console licence \"{$this->licenseName}\". Consoles using it will stop being verified:"
                : "Use this code to create the Management Console licence \"{$this->licenseName}\":")
            ->line("**{$this->code}**")
            ->line("The code expires in {$this->minutes} minutes.")
            ->line('If you did not request this, you can ignore this email and consider changing your password.');
    }
}
