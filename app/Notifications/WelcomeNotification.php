<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent once, when a new account's email is verified (or right away for Google sign-up). */
class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = rtrim(config('atglance.frontend_url'), '/');
        $name = trim((string) ($notifiable->name ?? ''));

        return (new MailMessage)
            ->subject('Welcome to AtGlance')
            ->greeting($name !== '' ? "Hi {$name}," : 'Hi,')
            ->line('Your AtGlance account is ready. Thanks for signing up!')
            ->line('From your dashboard you can download the AtGlance CLI and the self-hosted Management Console, and create a licence key for your Console.')
            ->action('Open your dashboard', $site.'/dashboard')
            ->line("Need a hand? The docs ({$site}/docs) cover install and setup, and you can reach us any time at {$site}/contact.");
    }
}
