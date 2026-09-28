<?php

namespace App\Filament\Resources\Users\Tables;

use App\Http\Controllers\Api\LicenseController;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state) => $state === User::ROLE_ADMIN ? 'warning' : 'gray'),
                TextColumn::make('plan')
                    ->badge()
                    ->formatStateUsing(fn (User $record) => $record->planLabel())
                    ->color(fn (string $state) => $state === 'free' ? 'gray' : 'success'),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->state(fn (User $record) => $record->hasVerifiedEmail()),
                TextColumn::make('tokens_count')
                    ->label('Licences')
                    ->counts(['tokens' => fn ($q) => $q->whereJsonContains('abilities', LicenseController::ABILITY)]),
                TextColumn::make('auth_method')
                    ->badge(),
                TextColumn::make('github_login')
                    ->label('GitHub')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    User::ROLE_USER => 'User',
                    User::ROLE_ADMIN => 'Admin',
                ]),
                SelectFilter::make('plan')->options(
                    collect(config('atglance.plans'))->map(fn (array $p) => $p['label'])->all()
                ),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('verifyEmail')
                    ->label('Activate')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (User $record) => ! $record->hasVerifiedEmail())
                    ->requiresConfirmation()
                    ->modalHeading('Activate account without email verification?')
                    ->modalDescription(fn (User $record) => "Marks {$record->email} as verified.")
                    ->action(function (User $record): void {
                        $record->markEmailAsVerified();
                        Notification::make()->title("{$record->email} activated")->success()->send();
                    }),
                EditAction::make(),
            ]);
    }
}
