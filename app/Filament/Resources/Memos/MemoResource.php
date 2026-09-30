<?php

namespace App\Filament\Resources\Memos;

use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use App\Filament\Resources\Horses\HorseResource;
use App\Filament\Resources\Memos\Pages\ListMemos;
use App\Filament\Resources\Memos\Pages\ViewMemo;
use App\Models\HorseEvent;
use App\Models\Memo;
use App\Support\SpeechDebug;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class MemoResource extends Resource
{
    protected static ?string $model = Memo::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $navigationLabel = 'Memos';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('status')->badge(),
                TextEntry::make('created_at')->dateTime()->label('Uploaded'),
                TextEntry::make('recorded_at')->dateTime()->placeholder('—'),
                TextEntry::make('size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextEntry::make('mime'),
                TextEntry::make('audio')
                    ->label('Audio')
                    ->state(fn (Memo $record): string => route('memos.audio', $record))
                    ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                        '<audio controls preload="none" src="'.e($state).'" style="width:100%"></audio>'
                    ))
                    ->html()
                    ->visible(fn (Memo $record): bool => filled($record->disk_path) && Storage::disk($record->disk)->exists($record->disk_path))
                    ->columnSpanFull(),
                TextEntry::make('transcript')
                    ->placeholder('Not transcribed yet.')
                    ->columnSpanFull(),
                TextEntry::make('log_status')->badge()->label('Horse log'),
                TextEntry::make('log_error')
                    ->placeholder('—')
                    ->visible(fn (Memo $record): bool => $record->log_status === HorseLogStatus::Failed)
                    ->columnSpanFull(),
                RepeatableEntry::make('horseEvents')
                    ->label('Horse events')
                    ->placeholder('No horses mentioned.')
                    ->contained()
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('horse.name')
                            ->label('Horse')
                            ->url(fn (HorseEvent $record): string => HorseResource::getUrl('view', ['record' => $record->horse_id])),
                        TextEntry::make('occurred_on')->date()->placeholder('—')->label('When'),
                        TextEntry::make('retracted_at')
                            ->label('Status')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (): string => 'Corrected')
                            ->visible(fn (HorseEvent $record): bool => $record->retracted_at !== null),
                        TextEntry::make('summary')->columnSpanFull(),
                        TextEntry::make('detail')->placeholder('—')->columnSpanFull(),
                    ]),
                TextEntry::make('error')
                    ->placeholder('—')
                    ->visible(fn (Memo $record): bool => $record->status === MemoStatus::Failed)
                    ->columnSpanFull(),
                TextEntry::make('speech_debug')
                    ->label('Speech-to-text process')
                    ->state(fn (): HtmlString => new HtmlString(
                        collect(SpeechDebug::lines())
                            ->map(fn (string $line): string => '<p>'.e($line).'</p>')
                            ->implode('')
                    ))
                    ->html()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->label('Uploaded')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('log_status')->badge()->label('Horse log')->sortable(),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('transcript')->limit(60)->placeholder('—')->searchable(),
                TextColumn::make('recorded_at')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('error')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(MemoStatus::class),
            ])
            ->recordActions([
                Action::make('queueTranscription')
                    ->label('Queue again')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (Memo $record): bool => $record->status === MemoStatus::Failed)
                    ->action(function (Memo $record): void {
                        TranscribeMemoActions::queue($record);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('queueSelected')
                        ->label('Queue transcription')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Collection $records): void {
                            TranscribeMemoActions::queueMany($records);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['horseEvents.horse']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemos::route('/'),
            'view' => ViewMemo::route('/{record}'),
        ];
    }
}
