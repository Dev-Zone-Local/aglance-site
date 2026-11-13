<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Models\BlogPost;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Post')
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?BlogPost $record) {
                                if (! $record && blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Address')
                            ->prefix('/blog/')
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->helperText('Made from the title when empty. Changing it breaks old links.'),
                        Textarea::make('excerpt')
                            ->label('Summary')
                            ->rows(2)
                            ->maxLength(300)
                            ->helperText('Shown on the blog list. Empty = the start of the post.'),
                        MarkdownEditor::make('body')
                            ->label('Post')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('blog/attachments')
                            ->helperText('Markdown: ## headings, lists, links, `code` and ```bash blocks. Drop images in to upload them.'),
                    ]),
                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make('Publishing')->schema([
                        Select::make('status')->options(BlogPost::STATUSES)->default('draft')->required()->native(false),
                        DateTimePicker::make('published_at')
                            ->label('Publish date')
                            ->native(false)
                            ->seconds(false)
                            ->helperText('Empty = now, when published. A future date schedules the post.'),
                        Select::make('author_id')
                            ->label('Author')
                            ->options(fn () => User::where('role', User::ROLE_ADMIN)->orderBy('name')->pluck('name', 'id'))
                            ->default(fn () => auth()->id())
                            ->searchable(),
                        TagsInput::make('tags')->placeholder('release, how-to')->splitKeys(['Tab', ',']),
                    ]),
                    Section::make('Cover image')->schema([
                        FileUpload::make('cover_image')
                            ->hiddenLabel()
                            ->image()
                            ->disk('public')
                            ->directory('blog')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->helperText('1600×900 px works best. Also the sharing image of the post.'),
                    ]),
                    Section::make('Search and sharing')->collapsed()->schema([
                        TextInput::make('seo_title')->label('Title for search')->maxLength(70)->helperText('Empty = the post title.'),
                        Textarea::make('seo_description')->label('Description for search')->rows(2)->maxLength(170)->helperText('Empty = the summary.'),
                    ]),
                ]),
            ]),
        ]);
    }
}
