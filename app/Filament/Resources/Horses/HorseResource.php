<?php

namespace App\Filament\Resources\Horses;

use App\Filament\Resources\Horses\Pages\ListHorses;
use App\Filament\Resources\Horses\Pages\ViewHorse;
use App\Filament\Resources\Memos\MemoResource;
use App\Models\Horse;
use App\Models\HorseEvent;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HorseResource extends Resource
{
    protected static ?string $model = Horse::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Horses';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('aliases')
                    ->state(fn (Horse $record): string => $record->aliases === []
                        ? '—'
                        : implode(', ', $record->aliases)),
                TextEntry::make('updated_at')->dateTime()->label('Updated'),
                TextEntry::make('knowledge')
                    ->placeholder('No knowledge yet.')
                    ->markdown()
                    ->columnSpanFull(),
                RepeatableEntry::make('events')
                    ->placeholder('No events yet.')
                    ->contained()
                    ->columnSpanFull()
                    ->components([
                        TextEntry::make('occurred_on')->date()->placeholder('—')->label('When'),
                        TextEntry::make('retracted_at')
                            ->label('Status')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (): string => 'Corrected')
                            ->visible(fn (HorseEvent $record): bool => $record->retracted_at !== null),
                        TextEntry::make('summary'),
                        TextEntry::make('detail')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('memo_id')
                            ->label('Memo')
                            ->formatStateUsing(fn (): string => 'Open memo')
                            ->url(fn (HorseEvent $record): string => MemoResource::getUrl('view', ['record' => $record->memo_id])),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('events_count')->label('Events')->sortable(),
                TextColumn::make('updated_at')->dateTime()->label('Updated')->sortable(),
            ])
            ->recordUrl(fn (Horse $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('events');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHorses::route('/'),
            'view' => ViewHorse::route('/{record}'),
        ];
    }
}
