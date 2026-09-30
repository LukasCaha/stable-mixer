<?php

namespace App\Filament\Resources\Memos\Pages;

use App\Filament\Resources\Memos\MemoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMemo extends ViewRecord
{
    protected static string $resource = MemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (): string => route('memos.audio', [
                    'memo' => $this->getRecord(),
                    'download' => 1,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
