<?php

namespace Tests\Feature;

use App\Ai\Agents\HorseLogAgent;
use App\Ai\Agents\QuestionAgent;
use App\Contracts\SpeechTranscriber;
use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use App\Jobs\TranscribeMemo;
use App\Models\Answer;
use App\Models\Horse;
use App\Models\Memo;
use App\Models\Stable;
use App\Models\User;
use App\Services\QuestionAnswerer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Transcription;
use RuntimeException;
use Tests\TestCase;

class QuestionAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_about_a_known_record_is_stored_after_the_farm_log(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Willow was shod today. When was she last shod?']);
        HorseLogAgent::fake([[
            'records' => [
                [
                    'kind' => 'animal',
                    'name' => 'Willow',
                    'aliases' => [],
                    'knowledge' => 'Willow was shod on 30 September 2026.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Shod',
                            'detail' => 'Today.',
                        ],
                    ],
                ],
            ],
        ]]);
        QuestionAgent::fake([[
            'questions' => [
                [
                    'question' => 'When was Willow last shod?',
                    'answer' => 'Willow was shod on 30 September 2026.',
                ],
            ],
        ]]);

        $stable = Stable::factory()->create();
        Horse::factory()->for($stable)->create([
            'name' => 'Willow',
            'knowledge' => 'Willow was last shod in March.',
        ]);
        $memo = Memo::factory()->for($stable)->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'status' => MemoStatus::Queued,
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $job = new TranscribeMemo($memo);
        $job->handle(app(SpeechTranscriber::class));

        $answer = Answer::query()->firstOrFail();
        $this->assertSame($stable->id, $answer->stable_id);
        $this->assertSame($memo->id, $answer->memo_id);
        $this->assertSame('When was Willow last shod?', $answer->question);
        $this->assertSame('Willow was shod on 30 September 2026.', $answer->answer);

        QuestionAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Willow was shod on 30 September 2026.')
                && str_contains($prompt->prompt, 'When was she last shod?')
                && str_contains($prompt->agent->instructions(), QuestionAnswerer::Unknown);
        });
    }

    public function test_a_transcript_with_no_question_stores_none_and_replaces_older_answers(): void
    {
        QuestionAgent::fake([['questions' => []]]);

        $memo = Memo::factory()->done()->create([
            'transcript' => 'Willow was turned out.',
        ]);
        Answer::query()->create([
            'stable_id' => $memo->stable_id,
            'memo_id' => $memo->id,
            'question' => 'Old question?',
            'answer' => 'Old answer.',
        ]);

        app(QuestionAnswerer::class)->answer($memo);

        $this->assertSame(0, Answer::query()->count());
        QuestionAgent::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Willow was turned out.'));
    }

    public function test_an_unknown_question_is_stored_as_the_records_do_not_say(): void
    {
        QuestionAgent::fake([[
            'questions' => [
                [
                    'question' => 'What is the weather in Paris?',
                    'answer' => QuestionAnswerer::Unknown,
                ],
            ],
        ]]);

        $memo = Memo::factory()->done()->create([
            'transcript' => 'What is the weather in Paris?',
        ]);

        app(QuestionAnswerer::class)->answer($memo);

        $answer = Answer::query()->firstOrFail();
        $this->assertSame('What is the weather in Paris?', $answer->question);
        $this->assertSame(QuestionAnswerer::Unknown, $answer->answer);
    }

    public function test_an_empty_transcript_clears_answers_without_asking_the_model(): void
    {
        $memo = Memo::factory()->done()->create([
            'transcript' => '   ',
        ]);
        Answer::query()->create([
            'stable_id' => $memo->stable_id,
            'memo_id' => $memo->id,
            'question' => 'Old question?',
            'answer' => 'Old answer.',
        ]);

        app(QuestionAnswerer::class)->answer($memo);

        $this->assertSame(0, Answer::query()->count());
        QuestionAgent::assertPromptedTimes(0);
    }

    public function test_a_failed_farm_log_still_stores_answers(): void
    {
        Storage::fake('memos');
        Transcription::fake(['When was Willow last shod?']);
        HorseLogAgent::fake(function (): never {
            throw new RuntimeException('model down');
        });
        QuestionAgent::fake([[
            'questions' => [
                [
                    'question' => 'When was Willow last shod?',
                    'answer' => 'March.',
                ],
            ],
        ]]);

        $memo = Memo::factory()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/1/note.m4a',
            'status' => MemoStatus::Queued,
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $job = new TranscribeMemo($memo);
        $job->handle(app(SpeechTranscriber::class));

        $memo->refresh();
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame(HorseLogStatus::Failed, $memo->log_status);
        $this->assertSame('March.', Answer::query()->firstOrFail()->answer);
    }

    public function test_answers_are_limited_to_the_tenant(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $other = Stable::factory()->create(['tenant_code' => 'ZZ99YY88']);
        $memo = Memo::factory()->for($stable)->done()->create();
        $otherMemo = Memo::factory()->for($other)->done()->create();
        Answer::query()->create([
            'stable_id' => $stable->id,
            'memo_id' => $memo->id,
            'question' => 'When was Willow shod?',
            'answer' => 'Tuesday.',
        ]);
        Answer::query()->create([
            'stable_id' => $other->id,
            'memo_id' => $otherMemo->id,
            'question' => 'Secret?',
            'answer' => 'Do not show this.',
        ]);
        Memo::factory()->for($stable)->create(['status' => MemoStatus::Processing]);

        $this->getJson('/api/v1/answers', ['X-Tenant' => 'a1b2c3d4'])
            ->assertOk()
            ->assertJsonPath('pending', true)
            ->assertJsonCount(1, 'answers')
            ->assertJsonPath('answers.0.question', 'When was Willow shod?')
            ->assertJsonPath('answers.0.answer', 'Tuesday.')
            ->assertJsonMissing(['question' => 'Secret?']);

        $owner = User::factory()->for($stable)->owner()->create();

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/memos/'.$memo->id)
            ->assertOk()
            ->assertSee('When was Willow shod?')
            ->assertSee('Tuesday.')
            ->assertDontSee('Secret?');
    }

    public function test_an_unknown_tenant_cannot_read_answers(): void
    {
        $this->getJson('/api/v1/answers', ['X-Tenant' => 'NOPE1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant']);
    }
}
