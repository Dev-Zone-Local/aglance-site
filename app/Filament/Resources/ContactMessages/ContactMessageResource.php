<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Support\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Inbox for messages sent with the /contact form. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $modelLabel = 'message';

    protected static ?string $slug = 'messages';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', 'new')->count();

        return $new ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'new' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (ContactMessage $record) => $record->subject)
                ->description(fn (ContactMessage $record) => $record->reference().' · '.$record->created_at->format('d M Y, H:i'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->copyable()->url(fn (ContactMessage $r) => 'mailto:'.$r->email),
                        TextEntry::make('topic')->badge()->formatStateUsing(fn (string $state) => ContactMessage::TOPICS[$state] ?? $state),
                        TextEntry::make('product')->badge()->color('gray')
                            ->formatStateUsing(fn (?string $state) => ContactMessage::PRODUCTS[$state] ?? $state)
                            ->placeholder('Not given'),
                    ]),
                    TextEntry::make('message')
                        ->hiddenLabel()
                        ->extraAttributes(['style' => 'white-space: pre-wrap; line-height: 1.6;'])
                        ->columnSpanFull(),
                ]),
            Section::make('Handling')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('status')->badge()
                        ->color(fn (string $state) => self::statusColor($state))
                        ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state),
                    TextEntry::make('emailed_at')->label('Emailed to support')->dateTime('d M Y, H:i')->placeholder('Not emailed (check mail settings)'),
                    TextEntry::make('user.email')->label('Registered account')->placeholder('Guest'),
                    TextEntry::make('admin_notes')->label('Internal notes')->placeholder('None')->columnSpanFull()
                        ->extraAttributes(['style' => 'white-space: pre-wrap;']),
                    TextEntry::make('ip')->label('IP address')->placeholder('—'),
                    TextEntry::make('user_agent')->label('Browser')->placeholder('—')->columnSpan(2),
                ]),
            Section::make('Replies')
                ->description('Emails sent to the sender with the Reply button, newest first.')
                ->columnSpanFull()
                ->visible(fn (ContactMessage $record) => $record->replies()->exists())
                ->schema([
                    RepeatableEntry::make('replies')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('sent_at')
                                ->hiddenLabel()
                                ->badge()
                                ->color(fn (ContactMessageReply $r) => $r->sent_at ? 'success' : 'danger')
                                ->state(fn (ContactMessageReply $r) => $r->sent_at
                                    ? 'Sent '.$r->sent_at->format('d M Y, H:i').($r->user ? ' by '.$r->user->name : '')
                                    : 'Not sent: '.\Illuminate\Support\Str::limit((string) $r->error, 150)),
                            TextEntry::make('body')
                                ->hiddenLabel()
                                ->extraAttributes(['style' => 'white-space: pre-wrap; line-height: 1.6;']),
                            ImageEntry::make('images')
                                ->hiddenLabel()
                                ->disk('public')
                                ->imageHeight(120)
                                ->visible(fn (ContactMessageReply $r) => filled($r->images)),
                        ]),
                ]),
        ]);
    }

    /** "Update" action: status + internal notes. Used on the list and the view page. */
    public static function updateAction(): Action
    {
        return Action::make('update')
            ->label('Status & notes')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->fillForm(fn (ContactMessage $record) => ['status' => $record->status, 'admin_notes' => $record->admin_notes])
            ->schema([
                Select::make('status')->options(ContactMessage::STATUSES)->required()->native(false),
                Textarea::make('admin_notes')->label('Internal notes (not sent to the user)')->rows(4)->maxLength(5000),
            ])
            ->action(fn (ContactMessage $record, array $data) => $record->update($data));
    }

    /**
     * "Reply" action: text + optional images, emailed to the sender in the branded template
     * from the From address in Email (SMTP) settings. Every attempt is kept under "Replies".
     */
    public static function replyAction(): Action
    {
        return Action::make('reply')
            ->label('Reply')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('primary')
            ->modalHeading(fn (ContactMessage $r) => "Reply to {$r->name}")
            ->modalDescription(fn (ContactMessage $r) => "To {$r->email} · Subject: Re: {$r->subject} {$r->reference()}")
            ->modalSubmitActionLabel('Send reply')
            ->modalWidth('2xl')
            ->schema([
                Textarea::make('body')
                    ->label('Message')
                    ->required()
                    ->rows(10)
                    ->maxLength(20000)
                    ->helperText('Sent as written. "Hi {name}," and the AtGlance header and footer are added for you.'),
                FileUpload::make('images')
                    ->label('Images')
                    ->image()
                    ->multiple()
                    ->maxFiles(5)
                    ->disk('public')
                    ->directory(ContactMessageReply::IMAGE_DIR)
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                    ->maxSize(5120)
                    ->reorderable()
                    ->helperText('Optional. Up to 5 images (PNG, JPG, WebP or GIF, 5 MB each), shown below your text.'),
            ])
            ->action(function (ContactMessage $record, array $data): void {
                $reply = $record->replies()->create([
                    'user_id' => auth()->id(),
                    'body' => $data['body'],
                    'images' => array_values($data['images'] ?? []) ?: null,
                ]);

                try {
                    // Use the saved SMTP settings even if the mailer was built earlier in this request.
                    app('mail.manager')->forgetMailers();
                    MailSettings::apply();

                    Mail::to($record->email, $record->name)->send(new ContactReplyMail($reply));
                } catch (Throwable $e) {
                    $reply->update(['error' => $e->getMessage()]);
                    Notification::make()->title('Reply not sent')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                $reply->update(['sent_at' => now()]);
                if ($record->status === 'new') {
                    $record->update(['status' => 'in_progress']);
                }
                Notification::make()->title("Reply sent to {$record->email}")->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ContactMessage $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => self::statusColor($state))
                    ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state),
                TextColumn::make('subject')
                    ->searchable()
                    ->weight(fn (ContactMessage $r) => $r->status === 'new' ? 'bold' : null)
                    ->description(fn (ContactMessage $r) => \Illuminate\Support\Str::limit($r->message, 90))
                    ->wrap(),
                TextColumn::make('name')
                    ->label('From')
                    ->searchable(['name', 'email'])
                    ->description(fn (ContactMessage $r) => $r->email),
                TextColumn::make('topic')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => ContactMessage::TOPICS[$state] ?? $state),
                TextColumn::make('product')->badge()->color('gray')
                    ->formatStateUsing(fn (?string $state) => ContactMessage::PRODUCTS[$state] ?? $state)
                    ->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable()
                    ->tooltip(fn (ContactMessage $r) => $r->created_at->format('d M Y, H:i')),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactMessage::STATUSES),
                SelectFilter::make('topic')->options(ContactMessage::TOPICS),
                SelectFilter::make('product')->options(ContactMessage::PRODUCTS),
            ])
            ->recordActions([
                ViewAction::make(),
                self::replyAction(),
                self::updateAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resolve')
                        ->label('Mark as resolved')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'resolved']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('spam')
                        ->label('Mark as spam')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'spam']))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
