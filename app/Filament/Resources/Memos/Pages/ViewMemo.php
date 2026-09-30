<?php

namespace App\Filament\Resources\Memos\Pages;

use App\Enums\HorseLogStatus;
use App\Filament\Resources\Memos\MemoResource;
use App\Filament\Resources\Memos\TranscribeMemoActions;
use App\Models\Memo;
use App\Services\HorseLogWriter;
use App\Services\QuestionAnswerer;
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
                ->label('Rebuild farm log')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (Memo $record): bool => filled($record->transcript))
                ->action(function (): void {
                    /** @var Memo $memo */
                    $memo = $this->getRecord();
                    app(HorseLogWriter::class)->record($memo);
                    app(QuestionAnswerer::class)->answer($memo->refresh());
                    $memo->refresh()->load(['horseEvents.horse', 'answers']);
                    $this->fillForm();

                    $notification = Notification::make();

                    if ($memo->log_status === HorseLogStatus::Done) {
                        $notification->title('Farm log saved')->success();
                    } elseif ($memo->log_status === HorseLogStatus::Skipped) {
                        $notification->title('Farm log skipped')->body('The transcript is empty.')->warning();
                    } else {
                        $notification->title('Farm log failed')->body($memo->log_error ?: 'Farm log failed.')->danger();
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
