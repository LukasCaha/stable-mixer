<?php

namespace Tests\Feature;

use App\Contracts\SpeechTranscriber;
use App\Enums\MemoStatus;
use App\Enums\StableLanguage;
use App\Jobs\TranscribeMemo;
use App\Models\Horse;
use App\Models\HorseEvent;
use App\Models\Memo;
use App\Models\Stable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;
use Tests\TestCase;

class StableLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stable_defaults_to_english_and_can_be_czech(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);

        $this->assertSame(StableLanguage::English, $stable->language);

        $stable->update(['language' => StableLanguage::Czech]);

        $this->getJson('/api/v1/stables/A1B2C3D4')
            ->assertOk()
            ->assertJsonPath('language', 'cs');
    }

    public function test_transcription_uses_the_stable_language(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Kolik mám koní?']);

        $stable = Stable::factory()->create(['language' => StableLanguage::Czech]);
        $memo = Memo::factory()->for($stable)->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'status' => MemoStatus::Queued,
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        (new TranscribeMemo($memo))->handle(app(SpeechTranscriber::class));

        Transcription::assertGenerated(function (TranscriptionPrompt $prompt): bool {
            return $prompt->language === 'cs';
        });
    }

    public function test_records_are_limited_to_the_tenant(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $other = Stable::factory()->create(['tenant_code' => 'ZZ99YY88']);
        $horse = Horse::factory()->for($stable)->create([
            'name' => 'Willow',
            'knowledge' => 'Grey mare.',
        ]);
        HorseEvent::query()->create([
            'horse_id' => $horse->id,
            'memo_id' => Memo::factory()->for($stable)->done()->create()->id,
            'occurred_on' => '2026-09-30',
            'summary' => 'Shod',
            'detail' => 'Front feet.',
        ]);
        Horse::factory()->for($other)->create([
            'name' => 'Secret',
            'knowledge' => 'Do not show this.',
        ]);

        $this->getJson('/api/v1/records', ['X-Tenant' => 'A1B2C3D4'])
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.name', 'Willow')
            ->assertJsonPath('records.0.knowledge', 'Grey mare.')
            ->assertJsonPath('records.0.events.0.summary', 'Shod')
            ->assertJsonMissing(['name' => 'Secret']);
    }
}
