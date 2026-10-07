<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ReleasePublishedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails all verified users who did not unsubscribe about a new release.
 * Sent synchronously in chunks, so it works without a queue worker.
 */
class ReleaseNotifier
{
    /** @return array{sent: int, failed: int} */
    public static function send(string $product, array $release): array
    {
        $sent = 0;
        $failed = 0;
        $notification = new ReleasePublishedNotification(
            Downloads::PRODUCTS[$product],
            $release['version'],
            $release['checksum'] ?? null,
            $release['notes'] ?? null,
            $release['summary'] ?? null,
            $release['title'] ?? null,
            rtrim(config('atglance.frontend_url'), '/').'/releases?product='.$product.'#'.Downloads::anchor($product, $release['version']),
        );

        User::query()
            ->whereNotNull('email_verified_at')
            ->where('notify_updates', true)
            ->where('email', 'not like', '%@users.noreply.github.com')
            ->chunkById(100, function ($users) use ($notification, &$sent, &$failed) {
                foreach ($users as $user) {
                    try {
                        $user->notify($notification);
                        $sent++;
                    } catch (Throwable $e) {
                        $failed++;
                        Log::error('Could not send release email', ['user' => $user->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return ['sent' => $sent, 'failed' => $failed];
    }
}
