<?php

namespace App\Filament\Support;

use App\Models\License;
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
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])))
            ->filters([
                SelectFilter::make('verification')
                    ->label('Code')
                    ->options(['verified' => 'Code entered', 'unverified' => 'Awaiting code'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'verified' => $query->whereNotNull('confirmed_at'),
                        'unverified' => $query->whereNull('confirmed_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                LicenseActions::approve(),
                LicenseActions::decline(),
                LicenseActions::revoke(),
            ])
            ->toolbarActions([
                LicenseActions::approveBulk(),
            ]);
    }
}
