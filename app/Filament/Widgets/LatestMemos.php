<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Memos\MemoResource;
use App\Models\Memo;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

class LatestMemos extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest memos')
            ->query(
                Memo::query()
                    ->whereBelongsTo(Filament::getTenant(), 'stable')
                    ->latest()
            )
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('created_at')->since()->label('Uploaded'),
                TextColumn::make('status')->badge(),
                TextColumn::make('size')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                TextColumn::make('transcript')->limit(80)->placeholder('—'),
            ])
            ->recordUrl(fn (Memo $record): string => MemoResource::getUrl('view', ['record' => $record]));
    }
}
