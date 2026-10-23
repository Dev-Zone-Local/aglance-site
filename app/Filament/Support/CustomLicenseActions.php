<?php

namespace App\Filament\Support;

use App\Filament\Pages\LicensePolicyPage;
use App\Models\CustomLicensePolicy;
use App\Models\User;
use App\Support\LicensePolicy\PolicyAudit;
use App\Support\LicensePolicy\PolicyResolver;
use App\Support\LicensePolicy\PolicyRules;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Users → Create Custom License / Edit / Revoke. The custom policy is attached to the user,
 * who then generates the file from their dashboard: the 3-hour activation window starts at
 * generation, so it does not run out while a file is passed along.
 */
class CustomLicenseActions
{
    private const FIELDS = ['max_licences', 'install_id_mode', 'premium_features', 'max_nodes', 'max_users', 'validity_value', 'validity_unit', 'expires_on',
        'activation_window_minutes', 'install_id', 'notes'];

    public static function active(User $user): ?CustomLicensePolicy
    {
        return CustomLicensePolicy::query()->active()->where('user_id', $user->id)->latest('id')->first();
    }

    public static function edit(): Action
    {
        return Action::make('customLicense')
            ->label(fn (User $record) => self::active($record) ? 'Edit custom license' : 'Create custom license')
            ->icon(Heroicon::OutlinedDocumentPlus)
            ->color('info')
            ->modalHeading(fn (User $record) => (self::active($record) ? 'Edit' : 'Create').' custom license for '.$record->email)
            ->modalDescription('The user generates the file from their dashboard with these values. Already downloaded files do not change.')
            ->modalWidth('3xl')
            ->fillForm(function (User $record): array {
                $custom = self::active($record);
                if ($custom) {
                    return $custom->only(self::FIELDS) + ['expires_on' => $custom->expires_on?->toDateString()];
                }
                $resolved = PolicyResolver::for($record);

                return [
                    'max_licences' => $resolved['max_licences'],
                    'install_id_mode' => $resolved['install_id_mode'],
                    'premium_features' => $resolved['premium_features'],
                    'max_nodes' => $resolved['max_nodes'],
                    'max_users' => $resolved['max_users'],
                    'validity_value' => $resolved['validity_months'] % 12 === 0 ? intdiv($resolved['validity_months'], 12) : $resolved['validity_months'],
                    'validity_unit' => $resolved['validity_months'] % 12 === 0 ? 'years' : 'months',
                    'activation_window_minutes' => $resolved['activation_window_minutes'],
                ];
            })
            ->schema([
                Grid::make(2)->schema(array_merge(LicensePolicyPage::policyFields(), [
                    DatePicker::make('expires_on')->label('Explicit expiry date (optional)')->native(false)
                        ->minDate(now()->addDay())->maxDate(now()->addMonths(PolicyRules::MAX_VALIDITY_MONTHS))
                        ->helperText('Overrides the validity above.'),
                    TextInput::make('install_id')->label('Installation ID (optional)')->uuid()
                        ->helperText('Binds every file of this user to one console.'),
                    Textarea::make('notes')->label('Internal notes')->rows(3)->maxLength(2000)->columnSpanFull(),
                ])),
            ])
            ->action(function (User $record, array $data): void {
                $data = PolicyRules::validate($data, true);
                $values = array_intersect_key($data, array_flip(self::FIELDS)) + ['expires_on' => null, 'install_id' => null, 'notes' => null];
                $adminId = auth()->id();

                DB::transaction(function () use ($record, $values, $adminId) {
                    $custom = self::active($record);
                    if ($custom) {
                        $old = $custom->only(self::FIELDS);
                        $custom->fill($values + ['updated_by_admin_id' => $adminId])->save();
                        PolicyAudit::record($adminId, 'custom_license_policy', $custom, 'Custom: '.$record->email, 'updated', $old, $custom->only(self::FIELDS));
                    } else {
                        $custom = CustomLicensePolicy::create($values + [
                            'user_id' => $record->id, 'created_by_admin_id' => $adminId, 'updated_by_admin_id' => $adminId,
                        ]);
                        PolicyAudit::record($adminId, 'custom_license_policy', $custom, 'Custom: '.$record->email, 'created', [], $custom->only(self::FIELDS));
                    }
                });

                Notification::make()->title('Custom license saved')->body("{$record->email} can now generate a file with it.")->success()->send();
            });
    }

    public static function revoke(): Action
    {
        return Action::make('revokeCustomLicense')
            ->label('Revoke custom license')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $record) => self::active($record) !== null)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Revoke the custom license of '.$record->email.'?')
            ->modalDescription('New files use the default policy again. Files already downloaded keep working until they expire: they are checked offline and cannot be revoked.')
            ->action(function (User $record): void {
                $custom = self::active($record);
                if (! $custom) {
                    return;
                }
                $custom->update(['revoked_at' => now(), 'revoked_by_admin_id' => auth()->id()]);
                PolicyAudit::record(auth()->id(), 'custom_license_policy', $custom, 'Custom: '.$record->email, 'revoked', [], []);
                Notification::make()->title('Custom license revoked')->success()->send();
            });
    }
}
