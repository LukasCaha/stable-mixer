<?php

namespace Tests\Feature;

use App\Contracts\SpeechTranscriber;
use App\Enums\MemoStatus;
use App\Jobs\TranscribeMemo;
use App\Models\Memo;
use App\Models\Stable;
use App\Services\GroqTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;
use RuntimeException;
use Tests\TestCase;

class TranscribeMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_stores_the_transcript(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Turn out the mare.']);

        $memo = Memo::factory()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/1/note.m4a',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $job = new TranscribeMemo($memo);
        $job->handle(app(SpeechTranscriber::class));

        $memo->refresh();
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame('Turn out the mare.', $memo->transcript);
        $this->assertNull($memo->error);

        Transcription::assertGenerated(function (TranscriptionPrompt $prompt): bool {
            return $prompt->provider->driver() === 'groq'
                && $prompt->model === 'whisper-large-v3-turbo';
        });
    }

    public function test_failed_hook_marks_the_memo_failed(): void
    {
        $memo = Memo::factory()->create(['status' => MemoStatus::Processing]);

        $job = new TranscribeMemo($memo);
        $job->failed(new RuntimeException('provider down'));

        $memo->refresh();
        $this->assertSame(MemoStatus::Failed, $memo->status);
        $this->assertSame('provider down', $memo->error);
    }

    public function test_sync_queue_failure_marks_the_memo_failed(): void
    {
        Storage::fake('memos');

        $this->app->bind(SpeechTranscriber::class, fn () => new class implements SpeechTranscriber
        {
            public function transcribe(Memo $memo): string
            {
                throw new RuntimeException('provider down');
            }
        });

        $memo = Memo::factory()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/1/note.m4a',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        try {
            TranscribeMemo::dispatch($memo);
            $this->fail('The sync queue should surface the transcription error.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider down', $exception->getMessage());
        }

        $memo->refresh();
        $this->assertSame(MemoStatus::Failed, $memo->status);
        $this->assertSame('provider down', $memo->error);
    }

    public function test_upload_transcribes_after_the_response(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Turn out the mare.']);

        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);

        $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('note.m4a', 20, 'audio/mp4'),
        ], [
            'X-Tenant' => 'A1B2C3D4',
            'Accept' => 'application/json',
        ])->assertCreated()
            ->assertJson(['status' => 'queued']);

        $memo = Memo::query()->firstOrFail();
        $this->assertSame($stable->id, $memo->stable_id);
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame('Turn out the mare.', $memo->transcript);
    }

    public function test_missing_key_explains_that_this_process_loaded_an_empty_value(): void
    {
        Storage::fake('memos');
        config(['stt.groq_key' => '']);

        $memo = Memo::factory()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/1/note.m4a',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GROQ_API_KEY is empty in this process.');

        app(GroqTranscriber::class)->transcribe($memo);
    }
}
