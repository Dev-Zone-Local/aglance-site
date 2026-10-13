<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->options([
                        User::ROLE_USER => 'User',
                        User::ROLE_ADMIN => 'Admin',
                    ])
                    ->required()
                    ->default(User::ROLE_USER)
                    // Admins cannot demote themselves and lock everyone out.
                    ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                Toggle::make('email_verified_at')
                    ->label('Email verified (account active)')
                    ->helperText('Turn on to activate the account without the user clicking the verification link.')
                    ->afterStateHydrated(fn (Toggle $component, ?User $record) => $component->state((bool) $record?->hasVerifiedEmail()))
                    // Keep the original verification time if it was already verified.
                    ->dehydrateStateUsing(fn (?bool $state, ?User $record) => $state ? ($record?->email_verified_at ?? now()) : null),
                Toggle::make('license_requires_approval')
                    ->label('Licences need admin approval')
                    ->helperText('Off (default): a licence works once the user enters the emailed code. On: an admin must also approve each licence, e.g. for test accounts.'),
                Select::make('plan')
                    ->options(collect(config('atglance.plans'))->map(fn (array $p) => $p['label'])->all())
                    ->required()
                    ->default(config('atglance.default_plan'))
                    ->helperText('Controls how many Management Console licences the user can create.'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(6)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                TextInput::make('auth_method')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                TextInput::make('github_login')
                    ->label('GitHub login')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
            ]);
    }
}
