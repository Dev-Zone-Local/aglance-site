<?php

namespace App\Filament\Resources\KnownIssues\Tables;

use App\Models\KnownIssue;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KnownIssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->columns([
                TextColumn::make('title')
                    ->label('Problem')
                    ->searchable()
                    ->wrap()
                    ->description(fn (KnownIssue $r) => $r->tags ? implode(' · ', $r->tags) : null),
                TextColumn::make('product')
                    ->label('Product')
                    ->badge()
                    ->color(fn (string $state) => $state === 'cli' ? 'info' : 'success')
                    ->formatStateUsing(fn (string $state) => KnownIssue::PRODUCTS[$state] ?? $state),
                TextColumn::make('platforms')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => KnownIssue::PLATFORMS[$state] ?? $state)
                    ->placeholder('All platforms'),
                ToggleColumn::make('is_published')->label('On website'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product')->label('Product')->options(KnownIssue::PRODUCTS),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (KnownIssue $r) => route('known-problems', ['product' => $r->product]).'#'.$r->slug, shouldOpenInNewTab: true),
                EditAction::make(),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->beforeReplicaSaved(function (KnownIssue $replica) {
                        $replica->title = $replica->title.' (copy)';
                        $replica->slug = null;
                        $replica->is_published = false;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
