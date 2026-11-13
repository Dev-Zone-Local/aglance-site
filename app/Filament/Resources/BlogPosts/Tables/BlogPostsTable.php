<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')->label('')->disk('public')->square()->imageSize(44),
                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->description(fn (BlogPost $r) => '/blog/'.$r->slug),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (BlogPost $r) => $r->isPublic() ? 'Published' : ($r->status === 'published' ? 'Scheduled' : 'Draft'))
                    ->color(fn (string $state) => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('tags')->badge()->color('gray')->toggleable(),
                TextColumn::make('author.name')->label('Author')->toggleable(),
                TextColumn::make('published_at')->label('Published')->date('d M Y')->sortable()->placeholder('—'),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(BlogPost::STATUSES),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (BlogPost $r) => $r->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
