<?php

namespace App\Filament\Support;

use App\Models\FeatureCatalog;
use App\Models\License;
use App\Models\User;
use App\Support\LicensePolicy\PolicyResolver;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Licence table shared by the central Licences page (withOwner) and the user edit page.
 */
class LicenseTable
{
    public static function configure(Table $table, bool $withOwner = false): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['activation', 'approver', 'tokenable']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No licences')
            ->columns(array_values(array_filter([
                $withOwner ? TextColumn::make('tokenable.email')
                    ->label('User')
                    ->description(fn (License $record) => $record->tokenable?->planLabel().' plan')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHasMorph(
                        'tokenable', '*', fn (Builder $q) => $q->where('email', 'like', "%{$search}%")
                    )) : null,
                TextColumn::make('name')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('status')
                    ->state(fn (License $record) => $record->status())
                    ->formatStateUsing(fn (string $state) => License::STATUS_LABELS[$state])
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        License::UNVERIFIED => 'gray',
                        License::UNDER_REVIEW => 'warning',
                        License::IN_USE => 'success',
                        License::EXPIRED => 'danger',
                        default => 'info',
                    }),
                IconColumn::make('confirmed_at')
                    ->label('Code confirmed')
                    ->state(fn (License $record) => $record->isConfirmed())
                    ->boolean()
                    ->tooltip(fn (License $record) => $record->isConfirmed()
                        ? 'User entered the emailed code'
                        : 'User has not entered the emailed code yet'),
                TextColumn::make('activation.org_name')
                    ->label('Organization')
                    ->placeholder('—')
                    ->description(fn (License $record) => $record->activation?->console_version
                        ? 'Console v'.$record->activation->console_version
                        : null),
                TextColumn::make('activation.last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never'),
                TextColumn::make('approved_at')
                    ->label('Approved')
                    ->dateTime()
                    ->description(fn (License $record) => $record->approver ? "by {$record->approver->email}" : null)
                    ->placeholder(fn (License $record) => $record->requiresApproval() ? 'Pending' : 'Not required'),
                // Premium features of the owner's policy: the bundle, or each individual grant.
                $withOwner ? TextColumn::make('premium')
                    ->label('Premium')
                    ->state(fn (License $record) => self::premiumLabel($record))
                    ->wrap() : null,
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (License $record) => $record->isExpired() ? 'danger' : null)
                    ->placeholder('Never'),
            ])))
            ->filters(array_values(array_filter([
                $withOwner ? SelectFilter::make('feature')
                    ->label('Premium feature')
                    ->options(fn () => FeatureCatalog::premiumOptions())
                    ->query(fn (Builder $query, array $data) => ($data['value'] ?? null)
                        ? $query->where('tokenable_type', User::class)->whereIn('tokenable_id', self::ownersWith((string) $data['value']))
                        : $query) : null,
                SelectFilter::make('verification')
                    ->label('Code')
                    ->options(['verified' => 'Code entered', 'unverified' => 'Pending'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'verified' => $query->whereNotNull('confirmed_at'),
                        'unverified' => $query->whereNull('confirmed_at'),
                        default => $query,
                    }),
            ])))
            ->recordActions([
                LicenseActions::approve(),
                LicenseActions::decline(),
                LicenseActions::revoke(),
            ])
            ->toolbarActions([
                LicenseActions::approveBulk(),
            ]);
    }

    /** "Premium Features (all)", "Remote Tunnel, NFS Storage" or "None", from the owner's policy. */
    public static function premiumLabel(License $record): string
    {
        $owner = $record->tokenable;
        if (! $owner instanceof User) {
            return '—';
        }
        $policy = PolicyResolver::for($owner);
        if (in_array(FeatureCatalog::BUNDLE_PREMIUM, $policy['bundles'], true)) {
            return 'Premium Features (all)';
        }
        $names = array_values(array_intersect_key(FeatureCatalog::premiumOptions(), array_flip($policy['features'])));

        return $names === [] ? 'None' : implode(', ', $names);
    }

    /** @return list<int> owners of licences whose policy includes $feature (bundle or individual) */
    public static function ownersWith(string $feature): array
    {
        $ids = License::query()->licenses()->where('tokenable_type', User::class)->distinct()->pluck('tokenable_id');

        return User::query()->whereIn('id', $ids)->get()
            ->filter(fn (User $user) => PolicyResolver::includes($user, $feature))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
