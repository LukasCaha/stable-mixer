<?php

namespace App\Filament\Resources\Memos\Pages;

use App\Enums\HorseLogStatus;
use App\Filament\Resources\Memos\MemoResource;
use App\Filament\Resources\Memos\TranscribeMemoActions;
use App\Models\Memo;
use App\Services\HorseLogWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            Action::make('rebuildHorseLog')
                ->label('Rebuild horse log')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (Memo $record): bool => filled($record->transcript))
                ->action(function (): void {
                    /** @var Memo $memo */
                    $memo = $this->getRecord();
                    app(HorseLogWriter::class)->record($memo);
                    $memo->refresh()->load('horseEvents.horse');
                    $this->fillForm();

                    $notification = Notification::make();

                    if ($memo->log_status === HorseLogStatus::Done) {
                        $notification->title('Horse log saved')->success();
                    } elseif ($memo->log_status === HorseLogStatus::Skipped) {
                        $notification->title('Horse log skipped')->body('The transcript is empty.')->warning();
                    } else {
                        $notification->title('Horse log failed')->body($memo->log_error ?: 'Horse log failed.')->danger();
                    }

                    $notification->send();
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
