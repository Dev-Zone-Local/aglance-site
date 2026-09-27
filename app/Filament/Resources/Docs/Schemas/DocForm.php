<?php

namespace App\Filament\Resources\Docs\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('URL: /docs/{slug}'),
                TextInput::make('section')
                    ->required()
                    ->default('Getting Started'),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                MarkdownEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
