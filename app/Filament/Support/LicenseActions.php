<?php

namespace App\Filament\Support;

use App\Models\License;
use App\Models\User;
use App\Notifications\LicenseApprovedNotification;
use App\Notifications\LicenseDeclinedNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as Toast;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin actions on licences, shared by the central Licences page and the user edit page.
 */
class LicenseActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            // Pending on the user's side (code not entered) or waiting for an admin (flagged user).
            ->visible(fn (License $record) => ! $record->isUsable())
            ->requiresConfirmation()
            ->modalHeading(fn (License $record) => "Approve licence \"{$record->name}\"?")
            ->modalDescription(fn (License $record) => $record->isConfirmed()
                ? 'The user will be emailed and their Management Console can then activate this licence.'
                : 'The user has not entered the emailed code yet. Approving skips that step: the licence works immediately and the user is emailed.')
            ->action(function (License $record): void {
                self::approveOne($record);
                Toast::make()->title("Licence \"{$record->name}\" approved")->success()->send();
            });
    }

    public static function decline(): Action
    {
        return Action::make('decline')
            ->label('Decline')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (License $record) => $record->needsApproval())
            ->modalHeading(fn (License $record) => "Decline licence \"{$record->name}\"?")
            ->modalDescription('The request is removed and the user is emailed. They can request a new licence later.')
            ->modalSubmitActionLabel('Decline')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional, sent to the user)')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (License $record, array $data): void {
                $name = $record->name;
                $owner = $record->tokenable;
                $record->delete();

                self::mail($owner, new LicenseDeclinedNotification($name, $data['reason'] ?? null));
                Toast::make()->title("Licence \"{$name}\" declined")->success()->send();
            });
    }

    public static function revoke(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Revoke')
            ->modalHeading(fn (License $record) => "Revoke licence \"{$record->name}\"?")
            ->modalDescription('The licence stops working immediately and any console using it is disconnected. This cannot be undone.')
            ->successNotificationTitle('Licence revoked');
    }

    public static function approveBulk(): BulkAction
    {
        return BulkAction::make('approveSelected')
            ->label('Approve selected')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $records->reject->isUsable()->each(fn (License $license) => self::approveOne($license));
                Toast::make()->title('Selected licences approved')->success()->send();
            });
    }

    /**
     * Make the licence usable: the admin vouches for it, so a missing emailed code is
     * bypassed and, for flagged users, the approval is recorded.
     */
    public static function approveOne(License $license): void
    {
        if ($license->isUsable()) {
            return;
        }

        if (! $license->isConfirmed()) {
            $license->confirm();
        }
        $license->approve(auth()->user());

        self::mail($license->tokenable, new LicenseApprovedNotification($license->name));
    }

    /** Emails must never break an admin action. */
    private static function mail(?User $user, Notification $notification): void
    {
        if (! $user || ! $user->hasDeliverableEmail()) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::error('Could not send licence email', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
