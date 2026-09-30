<?php

namespace App\Filament\Resources\Memos\Pages;

use App\Filament\Resources\Memos\MemoResource;
use App\Filament\Resources\Memos\TranscribeMemoActions;
use App\Models\Memo;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMemo extends ViewRecord
{
    protected static string $resource = MemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queueTranscription')
                ->label('Queue again')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(function (): void {
                    /** @var Memo $memo */
                    $memo = $this->getRecord();
                    TranscribeMemoActions::queue($memo);
                    $this->fillForm();
                }),
            Action::make('runNow')
                ->label('Run transcription now')
                ->icon(Heroicon::OutlinedPlay)
                ->requiresConfirmation()
                ->modalDescription('Calls the speech provider in this request and writes the transcript or the error onto this memo.')
                ->action(function (): void {
                    /** @var Memo $memo */
                    $memo = $this->getRecord();
                    TranscribeMemoActions::runNow($memo);
                    $this->fillForm();
                }),
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
