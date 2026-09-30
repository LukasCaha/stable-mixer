<?php

namespace App\Filament\Resources\Memos;

use App\Enums\MemoStatus;
use App\Jobs\TranscribeMemo;
use App\Models\Memo;
use App\Support\SpeechDebug;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Throwable;

class TranscribeMemoActions
{
    public static function queue(Memo $memo): void
    {
        self::enqueue($memo);

        if (config('queue.default') === 'sync') {
            self::notify($memo, null);

            return;
        }

        Notification::make()
            ->title('Added to the queue')
            ->body('The memo stays '.$memo->status->getLabel().' until a queue worker starts the job. '.self::queueStatus())
            ->warning()
            ->send();
    }

    /**
     * @param  Collection<int, Memo>  $memos
     */
    public static function queueMany(Collection $memos): void
    {
        $memos->each(fn (Memo $memo) => self::enqueue($memo));

        $done = $memos->filter(fn (Memo $memo): bool => $memo->status === MemoStatus::Done)->count();
        $failed = $memos->filter(fn (Memo $memo): bool => $memo->status === MemoStatus::Failed)->count();

        Notification::make()
            ->title('Queued '.$memos->count().' memos')
            ->body($done.' transcribed, '.$failed.' failed. '.self::queueStatus())
            ->send();
    }

    public static function runNow(Memo $memo): void
    {
        try {
            Bus::dispatchSync(new TranscribeMemo($memo));
        } catch (Throwable $exception) {
            $memo->refresh();
            self::notify($memo, $exception->getMessage());

            return;
        }

        $memo->refresh();
        self::notify($memo, null);
    }

    private static function notify(Memo $memo, ?string $exceptionMessage): void
    {
        $body = $memo->status === MemoStatus::Failed
            ? ($memo->error ?: $exceptionMessage ?: 'Transcription failed.')
            : 'Transcript saved.';

        $notification = Notification::make()
            ->title($memo->status === MemoStatus::Done ? 'Transcript saved' : 'Transcription failed')
            ->body($body);

        if ($memo->status === MemoStatus::Done) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    private static function enqueue(Memo $memo): void
    {
        try {
            TranscribeMemo::dispatch($memo);
        } catch (Throwable) {
            // The job's failed() hook already stored the error on the memo.
        }

        $memo->refresh();
    }

    private static function queueStatus(): string
    {
        $status = '';

        foreach (SpeechDebug::lines() as $line) {
            if (str_starts_with($line, 'Queue:') || str_starts_with($line, 'Jobs are waiting')) {
                $status = $line;
            }
        }

        return $status;
    }
}
