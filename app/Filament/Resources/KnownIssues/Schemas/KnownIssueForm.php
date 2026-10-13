<?php

namespace App\Filament\Resources\KnownIssues\Schemas;

use App\Models\KnownIssue;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class KnownIssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Problem')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->label('Problem (shown as the question)')
                        ->placeholder('The console works on IP:8000, but my domain shows no styles')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),
                    Select::make('product')
                        ->label('Product tag')
                        ->options(KnownIssue::PRODUCTS)
                        ->required()
                        ->default('console')
                        ->native(false),
                    CheckboxList::make('platforms')
                        ->options(KnownIssue::PLATFORMS)
                        ->columns(3)
                        ->helperText('Leave all unticked if it applies everywhere.'),
                    TagsInput::make('tags')
                        ->placeholder('Add a tag, e.g. proxy, https, licence')
                        ->splitKeys(['Tab', ','])
                        ->columnSpanFull(),
                    Textarea::make('symptom')
                        ->label('What you see')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Section::make('Solution')
                ->icon(Heroicon::OutlinedWrench)
                ->columnSpanFull()
                ->schema([
                    MarkdownEditor::make('solution')
                        ->hiddenLabel()
                        ->required()
                        ->helperText('Markdown: ### headings, lists, `code`, and ```bash / ```powershell / ```nginx blocks. Placeholders like <your-domain> are shown as written.')
                        ->columnSpanFull(),
                ]),
            Section::make('Display')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('slug')
                        ->label('Link name')
                        ->helperText('Used in the link /known-problems#name. Empty = made from the title.')
                        ->maxLength(120)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower first. You can also drag rows in the list.'),
                    Toggle::make('is_published')->label('Show on the website')->default(true)->inline(false),
                ]),
        ]);
    }
}
