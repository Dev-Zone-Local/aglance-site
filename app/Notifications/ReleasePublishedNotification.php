<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * "A new version of the CLI / Management Console is available", sent when an admin
 * publishes a release with "Notify users" ticked.
 */
class ReleasePublishedNotification extends Notification
{
    public function __construct(
        public readonly string $productName,
        public readonly string $version,
        public readonly ?string $checksum,
        public readonly ?string $notes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dashboard = rtrim(config('atglance.frontend_url'), '/').'/dashboard';
        $unsubscribe = URL::signedRoute('updates.unsubscribe', ['id' => $notifiable->getKey()]);

        $mail = (new MailMessage)
            ->subject("{$this->productName} {$this->version} is available")
            ->greeting("{$this->productName} {$this->version}")
            ->line("A new version of the {$this->productName} is available.");

        if ($this->notes) {
            $mail->line($this->notes);
        }
        if ($this->checksum) {
            $mail->line("SHA-256: `{$this->checksum}`");
        }

        return $mail
            ->action('Download from your dashboard', $dashboard)
            ->line("Don't want these emails? [Unsubscribe from update emails]({$unsubscribe}).");
    }
}
