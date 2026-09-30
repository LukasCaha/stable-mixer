<?php

namespace App\Jobs;

use App\Contracts\Failable;
use App\Contracts\SpeechTranscriber;
use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use App\Models\Memo;
use App\Services\HorseLogWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class TranscribeMemo implements Failable, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 180];

    public function __construct(public Memo $memo) {}

    public function handle(SpeechTranscriber $transcriber): void
    {
        $this->memo->update([
            'status' => MemoStatus::Processing,
            'error' => null,
        ]);

        $transcript = $transcriber->transcribe($this->memo);

        $this->memo->update([
            'status' => MemoStatus::Done,
            'transcript' => $transcript,
            'error' => null,
        ]);

        try {
            app(HorseLogWriter::class)->record($this->memo->refresh());
        } catch (Throwable $exception) {
            $this->memo->update([
                'log_status' => HorseLogStatus::Failed,
                'log_error' => str($exception->getMessage() ?: 'Horse log failed.')->limit(2000)->toString(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->memo->update([
            'status' => MemoStatus::Failed,
            'error' => str($exception?->getMessage() ?: 'Transcription failed.')->limit(2000)->toString(),
        ]);
    }
}
