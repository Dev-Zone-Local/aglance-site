<?php

namespace App\Filament\Resources\PricingPlans\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PricingPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('price')
                    ->required()
                    ->placeholder('$0'),
                TextInput::make('period')
                    ->placeholder('forever')
                    ->dehydrateStateUsing(fn (?string $state) => $state ?? ''),
                TextInput::make('cta')
                    ->label('Button label')
                    ->required()
                    ->default('Get started'),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('features')
                    ->simple(TextInput::make('feature')->required())
                    ->reorderable()
                    ->defaultItems(1)
                    ->columnSpanFull(),
                Toggle::make('highlighted'),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
