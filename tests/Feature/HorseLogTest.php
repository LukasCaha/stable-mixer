<?php

namespace Tests\Feature;

use App\Ai\Agents\HorseLogAgent;
use App\Contracts\SpeechTranscriber;
use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use App\Enums\SubjectKind;
use App\Filament\Resources\Memos\Pages\ViewMemo;
use App\Jobs\TranscribeMemo;
use App\Models\Horse;
use App\Models\HorseEvent;
use App\Models\Memo;
use App\Models\Stable;
use App\Models\User;
use App\Services\HorseLogWriter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Transcription;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class HorseLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_transcript_updates_several_horses(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Willow went out. Oak was lame.']);
        HorseLogAgent::fake([[
            'horses' => [
                [
                    'name' => 'Willow',
                    'aliases' => ['the grey mare'],
                    'knowledge' => 'Willow is a grey mare who lives in the north paddock.',
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Turned out',
                            'detail' => 'North paddock.',
                        ],
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Fed hay',
                            'detail' => 'Two flakes.',
                        ],
                    ],
                ],
                [
                    'name' => 'Oak',
                    'aliases' => [],
                    'knowledge' => 'Oak is lame in the left fore.',
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Noted lame',
                            'detail' => 'Left fore.',
                        ],
                    ],
                ],
            ],
        ]]);

        $stable = Stable::factory()->create();
        Horse::factory()->for($stable)->create([
            'name' => 'Birch',
            'knowledge' => 'Birch stays in the barn.',
        ]);
        $memo = Memo::factory()->for($stable)->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'transcript' => null,
            'status' => MemoStatus::Queued,
            'recorded_at' => '2026-09-30 08:00:00',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $job = new TranscribeMemo($memo);
        $job->handle(app(SpeechTranscriber::class));

        $memo->refresh();
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame(HorseLogStatus::Done, $memo->log_status);
        $this->assertSame(3, Horse::query()->count());
        $this->assertSame(3, HorseEvent::query()->count());

        $willow = Horse::query()->where('name', 'Willow')->firstOrFail();
        $oak = Horse::query()->where('name', 'Oak')->firstOrFail();
        $birch = Horse::query()->where('name', 'Birch')->firstOrFail();

        $this->assertSame('Willow is a grey mare who lives in the north paddock.', $willow->knowledge);
        $this->assertSame(['the grey mare'], $willow->aliases);
        $this->assertSame('Oak is lame in the left fore.', $oak->knowledge);
        $this->assertSame('Birch stays in the barn.', $birch->knowledge);
        $this->assertSame(0, $birch->events()->count());
        $this->assertSame(2, $willow->events()->count());
        $this->assertSame(1, $oak->events()->count());
        $this->assertTrue($memo->horseEvents()->where('summary', 'Noted lame')->exists());

        HorseLogAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return $prompt->provider->driver() === 'groq'
                && $prompt->model === 'openai/gpt-oss-20b'
                && str_contains($prompt->prompt, 'Willow went out. Oak was lame.')
                && str_contains($prompt->prompt, 'Birch');
        });
    }

    public function test_existing_horse_matches_regardless_of_case(): void
    {
        HorseLogAgent::fake([[
            'horses' => [
                [
                    'name' => 'Willow',
                    'aliases' => ['grey mare'],
                    'knowledge' => 'Willow was turned out today.',
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Turned out',
                            'detail' => 'Morning.',
                        ],
                    ],
                ],
            ],
        ]]);

        $stable = Stable::factory()->create();
        Horse::factory()->for($stable)->create([
            'name' => 'willow',
            'aliases' => ['the mare'],
            'knowledge' => 'Old brief.',
        ]);
        $memo = Memo::factory()->for($stable)->done()->create([
            'transcript' => 'Turned Willow out.',
        ]);

        app(HorseLogWriter::class)->record($memo);

        $this->assertSame(1, Horse::query()->count());
        $horse = Horse::query()->firstOrFail();
        $this->assertSame('willow', $horse->name);
        $this->assertSame('Willow was turned out today.', $horse->knowledge);
        $this->assertEqualsCanonicalizing(['the mare', 'grey mare'], $horse->aliases);
        $this->assertSame(1, $horse->events()->count());
    }

    public function test_running_the_writer_twice_keeps_one_set_of_events(): void
    {
        $payload = [[
            'horses' => [
                [
                    'name' => 'Willow',
                    'aliases' => [],
                    'knowledge' => 'Willow is sound.',
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Checked',
                            'detail' => 'Sound.',
                        ],
                    ],
                ],
            ],
        ]];

        HorseLogAgent::fake($payload);

        $memo = Memo::factory()->done()->create([
            'transcript' => 'Willow is sound.',
        ]);
        $writer = app(HorseLogWriter::class);
        $writer->record($memo);

        HorseLogAgent::fake($payload);
        $writer->record($memo->refresh());

        $this->assertSame(1, Horse::query()->count());
        $this->assertSame(1, HorseEvent::query()->where('memo_id', $memo->id)->count());
        $this->assertSame(HorseLogStatus::Done, $memo->refresh()->log_status);
    }

    public function test_agent_failure_keeps_the_transcript(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Willow is sound.']);
        HorseLogAgent::fake(function (mixed ...$arguments): never {
            throw new RuntimeException('model down');
        });

        $memo = Memo::factory()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/1/note.m4a',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');
        $job = new TranscribeMemo($memo);
        $job->handle(app(SpeechTranscriber::class));

        $memo->refresh();
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame('Willow is sound.', $memo->transcript);
        $this->assertSame(HorseLogStatus::Failed, $memo->log_status);
        $this->assertSame('model down', $memo->log_error);
        $this->assertSame(0, Horse::query()->count());
    }

    public function test_owner_can_read_the_horse_document_and_rebuild_the_log(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $other = Stable::factory()->create();
        $owner = User::factory()->for($stable)->owner()->create();
        $memo = Memo::factory()->for($stable)->done()->create([
            'transcript' => 'Willow was turned out.',
        ]);
        $horse = Horse::factory()->for($stable)->create([
            'name' => 'Willow',
            'knowledge' => 'Willow is a grey mare.',
        ]);
        HorseEvent::query()->create([
            'horse_id' => $horse->id,
            'memo_id' => $memo->id,
            'occurred_on' => '2026-09-30',
            'summary' => 'Turned out',
            'detail' => 'North paddock.',
        ]);
        Horse::factory()->for($other)->create([
            'name' => 'Secret Horse',
            'knowledge' => 'Do not show this.',
        ]);

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/records')
            ->assertOk()
            ->assertSee('Willow')
            ->assertDontSee('Secret Horse');

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/records/'.$horse->id)
            ->assertOk()
            ->assertSee('Willow is a grey mare.')
            ->assertSee('Turned out')
            ->assertSee('Open memo');

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/memos/'.$memo->id)
            ->assertOk()
            ->assertSee('Turned out')
            ->assertSee('Willow');

        HorseLogAgent::fake([[
            'horses' => [
                [
                    'name' => 'Willow',
                    'aliases' => [],
                    'knowledge' => 'Willow was turned out after lunch.',
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Turned out after lunch',
                            'detail' => 'North paddock.',
                        ],
                    ],
                ],
            ],
        ]]);

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($stable);

        Livewire::actingAs($owner)
            ->test(ViewMemo::class, ['record' => $memo->getRouteKey()])
            ->callAction('rebuildHorseLog')
            ->assertNotified('Farm log saved');

        $horse->refresh();
        $this->assertSame('Willow was turned out after lunch.', $horse->knowledge);
        $this->assertSame(1, $horse->events()->count());
        $this->assertSame('Turned out after lunch', $horse->events()->first()->summary);
    }

    public function test_a_later_memo_corrects_an_earlier_event(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();
        $oak = Horse::factory()->for($stable)->create([
            'name' => 'Oak',
            'knowledge' => 'Oak is lame in the left fore.',
        ]);
        $willow = Horse::factory()->for($stable)->create([
            'name' => 'Willow',
            'knowledge' => 'Willow is sound.',
        ]);
        $earlier = Memo::factory()->for($stable)->done()->create([
            'transcript' => 'Oak is lame.',
        ]);
        $wrong = HorseEvent::query()->create([
            'horse_id' => $oak->id,
            'memo_id' => $earlier->id,
            'occurred_on' => '2026-09-29',
            'summary' => 'Noted lame',
            'detail' => 'Left fore.',
        ]);
        $willowEvent = HorseEvent::query()->create([
            'horse_id' => $willow->id,
            'memo_id' => $earlier->id,
            'occurred_on' => '2026-09-29',
            'summary' => 'Sound',
            'detail' => 'No heat.',
        ]);
        $correction = Memo::factory()->for($stable)->done()->create([
            'transcript' => 'Oak is not lame. I was wrong yesterday.',
        ]);

        $payload = [[
            'horses' => [
                [
                    'name' => 'Oak',
                    'aliases' => [],
                    'knowledge' => 'Oak is sound. The lameness note was a mistake.',
                    'retract_event_ids' => [(string) $wrong->id, $willowEvent->id],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Not lame',
                            'detail' => 'Corrects the earlier note.',
                        ],
                    ],
                ],
            ],
        ]];

        HorseLogAgent::fake($payload);
        $writer = app(HorseLogWriter::class);
        $writer->record($correction);

        HorseLogAgent::assertPrompted(function (AgentPrompt $prompt) use ($wrong): bool {
            return str_contains($prompt->prompt, (string) $wrong->id)
                && str_contains($prompt->prompt, 'Noted lame')
                && str_contains($prompt->prompt, 'Oak is not lame.');
        });

        HorseLogAgent::fake($payload);
        $writer->record($correction->refresh());

        $wrong->refresh();
        $willowEvent->refresh();
        $oak->refresh();

        $this->assertNotNull($wrong->retracted_at);
        $this->assertSame($correction->id, $wrong->retracted_by_memo_id);
        $this->assertNull($willowEvent->retracted_at);
        $this->assertSame('Oak is sound. The lameness note was a mistake.', $oak->knowledge);
        $this->assertSame('Willow is sound.', $willow->knowledge);
        $this->assertSame(1, $oak->events()->whereNull('retracted_at')->count());
        $this->assertSame('Not lame', $oak->events()->whereNull('retracted_at')->first()->summary);

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/records/'.$oak->id)
            ->assertOk()
            ->assertSee('Oak is sound. The lameness note was a mistake.')
            ->assertSee('Corrected')
            ->assertSee('Not lame');
    }

    public function test_a_farm_memo_groups_sheep_a_tractor_and_tools(): void
    {
        HorseLogAgent::fake([[
            'records' => [
                [
                    'kind' => 'animal',
                    'name' => 'a sheep',
                    'aliases' => [],
                    'knowledge' => 'The flock gained one sheep.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Bought a sheep',
                            'detail' => 'Added to the flock.',
                        ],
                    ],
                ],
                [
                    'kind' => 'vehicle',
                    'name' => 'the tractor',
                    'aliases' => [],
                    'knowledge' => 'The tractor is broken.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Broken',
                            'detail' => 'Will not start.',
                        ],
                    ],
                ],
                [
                    'kind' => 'stock',
                    'name' => 'shovel',
                    'aliases' => [],
                    'knowledge' => 'The shovel is missing.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Lost the shovel',
                            'detail' => 'Last seen by the barn.',
                        ],
                    ],
                ],
                [
                    'kind' => 'animal',
                    'name' => 'Willow',
                    'aliases' => [],
                    'knowledge' => 'Willow was turned out.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Turned out',
                            'detail' => 'North paddock.',
                        ],
                    ],
                ],
            ],
        ]]);

        $memo = Memo::factory()->done()->create([
            'transcript' => 'We bought a sheep. The tractor is broken. I lost the shovel. Willow went out.',
        ]);

        app(HorseLogWriter::class)->record($memo);

        $this->assertSame(4, Horse::query()->count());

        $sheep = Horse::query()->where('name', 'Sheep')->firstOrFail();
        $tractor = Horse::query()->where('name', 'Tractor')->firstOrFail();
        $tools = Horse::query()->where('name', 'Tools')->firstOrFail();
        $willow = Horse::query()->where('name', 'Willow')->firstOrFail();

        $this->assertSame(SubjectKind::Animal, $sheep->kind);
        $this->assertSame(SubjectKind::Vehicle, $tractor->kind);
        $this->assertSame(SubjectKind::Stock, $tools->kind);
        $this->assertSame(SubjectKind::Animal, $willow->kind);
        $this->assertSame('Bought a sheep', $sheep->events()->first()->summary);
        $this->assertSame('Broken', $tractor->events()->first()->summary);
        $this->assertSame('Lost the shovel', $tools->events()->first()->summary);

        HorseLogAgent::fake([[
            'records' => [
                [
                    'kind' => 'stock',
                    'name' => 'pitchfork',
                    'aliases' => [],
                    'knowledge' => 'The pitchfork is in the shed. The shovel is still missing.',
                    'retract_event_ids' => [],
                    'events' => [
                        [
                            'occurred_on' => '2026-09-30',
                            'summary' => 'Found the pitchfork',
                            'detail' => 'In the shed.',
                        ],
                    ],
                ],
            ],
        ]]);

        $later = Memo::factory()->for($memo->stable)->done()->create([
            'transcript' => 'The pitchfork is in the shed.',
        ]);
        app(HorseLogWriter::class)->record($later);

        $this->assertSame(1, Horse::query()->where('kind', SubjectKind::Stock)->count());
        $tools->refresh();
        $this->assertSame(2, $tools->events()->count());
        $this->assertSame('The pitchfork is in the shed. The shovel is still missing.', $tools->knowledge);
    }

    public function test_the_horse_log_schema_is_strict_for_groq(): void
    {
        $agent = new HorseLogAgent;
        $schema = (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory), strict: true))->toSchema();
        $occurredOn = $schema['properties']['records']['items']['properties']['events']['items']['properties']['occurred_on'];

        $this->assertTrue(Strict::isAppliedTo($agent));
        $this->assertSame('string', $occurredOn['type']);
        $this->assertSame(['animal', 'vehicle', 'stock', 'place'], $schema['properties']['records']['items']['properties']['kind']['enum']);
        $this->assertFalse($schema['properties']['records']['items']['additionalProperties']);
        $this->assertSame(['reasoning_effort' => 'low'], $agent->providerOptions('groq'));
    }
}
