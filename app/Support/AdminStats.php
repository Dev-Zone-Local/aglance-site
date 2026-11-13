<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\DownloadFile;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseFile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Numbers for the admin dashboard: users by plan, licences in use, messages, activity.
 * Every number is a plain database count; nothing is estimated.
 */
class AdminStats
{
    public static function all(): array
    {
        return [
            'users' => self::users(),
            'licences' => self::licences(),
            'messages' => self::messages(),
            'heatmap' => self::heatmap(),
            'trend' => self::trend(),
            'downloads' => (int) DownloadFile::sum('download_count'),
        ];
    }

    /** Totals by plan, new sign-ups this week against last week, and a 30-day sign-up series. */
    public static function users(): array
    {
        $byPlan = User::query()->where('role', User::ROLE_USER)->selectRaw('plan, count(*) as n')->groupBy('plan')->pluck('n', 'plan')->all();
        $thisWeek = User::where('created_at', '>=', now()->subDays(7))->count();
        $lastWeek = User::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();

        return [
            'total' => User::count(),
            'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            'free' => (int) ($byPlan['free'] ?? 0),
            'enterprise' => (int) ($byPlan['enterprise'] ?? 0),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'new_week' => $thisWeek,
            'change' => self::change($thisWeek, $lastWeek),
            'daily' => self::daily(User::query(), 30),
        ];
    }

    /**
     * Licences: "in use" = activated online by a console, or turned into a licence file
     * (a file is used offline, so the key cannot be used again).
     */
    public static function licences(): array
    {
        $keys = License::query()->licenses();
        $total = (clone $keys)->count();
        $online = (clone $keys)->has('activation')->count();
        $byFile = (clone $keys)->whereHas('files', fn ($q) => $q->where('issued_by', 'user'))->count();
        $expired = (clone $keys)->whereNotNull('expires_at')->where('expires_at', '<', now())->count();
        $awaiting = (clone $keys)->awaitingApproval()->count();
        $active = LicenseActivation::where('last_seen_at', '>=', now()->subDay())->count();

        return [
            'total' => $total,
            'in_use' => $online + $byFile,
            'online' => $online,
            'files' => LicenseFile::count(),
            'by_file' => $byFile,
            'expired' => $expired,
            'awaiting' => $awaiting,
            'seen_today' => $active,
            'share' => $total ? (int) round(($online + $byFile) / $total * 100) : 0,
        ];
    }

    public static function messages(): array
    {
        $byStatus = ContactMessage::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $thisWeek = ContactMessage::where('created_at', '>=', now()->subDays(7))->count();
        $lastWeek = ContactMessage::whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])->count();

        return [
            'total' => array_sum($byStatus),
            'new' => (int) ($byStatus['new'] ?? 0),
            'in_progress' => (int) ($byStatus['in_progress'] ?? 0),
            'resolved' => (int) ($byStatus['resolved'] ?? 0),
            'week' => $thisWeek,
            'change' => self::change($thisWeek, $lastWeek),
            'latest' => ContactMessage::latest()->limit(5)->get(['id', 'name', 'email', 'subject', 'topic', 'product', 'status', 'created_at']),
        ];
    }

    /** Activity per day for the last 7 days: rows = what happened, columns = days. */
    public static function heatmap(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());
        $rows = [
            'Sign-ups' => self::countByDay(User::query(), $days),
            'Licences' => self::countByDay(License::query()->licenses(), $days),
            'Activations' => self::countByDay(LicenseActivation::query(), $days, 'activated_at'),
            'Licence files' => self::countByDay(LicenseFile::query(), $days),
            'Messages' => self::countByDay(ContactMessage::query(), $days),
        ];
        $max = max(1, ...array_map('max', array_values($rows)));

        return ['days' => $days->map(fn (Carbon $d) => $d->format('D'))->all(), 'rows' => $rows, 'max' => $max, 'total' => array_sum(array_map('array_sum', $rows))];
    }

    /** New users and new licences per month, last 6 months. */
    public static function trend(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $series = fn ($query) => $months->map(fn (Carbon $m) => (clone $query)->whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->count())->all();

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->format('M'))->all(),
            'users' => $series(User::query()),
            'licences' => $series(License::query()->licenses()),
        ];
    }

    /** Percent change, or null when there is nothing to compare with. */
    public static function change(int $now, int $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }

    /** @return list<int> one count per day, oldest first */
    private static function daily($query, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)->where('created_at', '>=', $from)->get(['created_at']);

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $rows) {
            $day = $from->copy()->addDays($i)->toDateString();

            return $rows->filter(fn ($r) => $r->created_at?->toDateString() === $day)->count();
        })->all();
    }

    /** @param  Collection<int, Carbon>  $days */
    private static function countByDay($query, Collection $days, string $column = 'created_at'): array
    {
        $rows = (clone $query)->where($column, '>=', $days->first())->pluck($column);

        return $days->map(fn (Carbon $d) => $rows->filter(fn ($at) => $at && Carbon::parse($at)->isSameDay($d))->count())->all();
    }
}
