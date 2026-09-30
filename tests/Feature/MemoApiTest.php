<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Jobs\TranscribeMemo;
use App\Models\Memo;
use App\Models\Stable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('memos');
        Queue::fake();
    }

    public function test_health(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_upload_with_tenant_header_queues_transcription(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);

        $response = $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('note.m4a', 20, 'audio/mp4'),
            'recorded_at' => '2026-09-30T12:00:00Z',
        ], [
            'X-Tenant' => 'a1b2c3d4',
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJson(['status' => 'queued'])
            ->assertJsonStructure(['id', 'status']);

        $memo = Memo::query()->firstOrFail();

        $this->assertSame($stable->id, $memo->stable_id);
        $this->assertSame(MemoStatus::Queued, $memo->status);
        $this->assertSame('memos', $memo->disk);
        $this->assertNotNull($memo->recorded_at);
        Storage::disk('memos')->assertExists($memo->disk_path);

        Queue::assertPushed(TranscribeMemo::class, function (TranscribeMemo $job) use ($memo): bool {
            return $job->memo->is($memo);
        });
    }

    public function test_upload_accepts_tenant_form_field(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'ZZ99YY88']);

        $this->post('/api/v1/memos', [
            'tenant' => 'zz99yy88',
            'file' => UploadedFile::fake()->create('note.m4a', 10, 'audio/mp4'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertSame($stable->id, Memo::query()->firstOrFail()->stable_id);
    }

    public function test_unknown_tenant_is_rejected(): void
    {
        $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('note.m4a', 10, 'audio/mp4'),
        ], [
            'X-Tenant' => 'NOPE1234',
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant']);

        $this->assertSame(0, Memo::query()->count());
    }

    public function test_inactive_tenant_is_rejected(): void
    {
        Stable::factory()->inactive()->create(['tenant_code' => 'DEADBEEF']);

        $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('note.m4a', 10, 'audio/mp4'),
        ], [
            'X-Tenant' => 'DEADBEEF',
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.tenant.0', 'This stable is not accepting memos.');
    }

    public function test_non_audio_file_is_rejected(): void
    {
        Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);

        $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ], [
            'X-Tenant' => 'A1B2C3D4',
            'Accept' => 'application/json',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_uploads_are_rate_limited_per_tenant(): void
    {
        config(['stt.rate_limit_per_minute' => 2]);

        Stable::factory()->create(['tenant_code' => 'RATE1234']);

        $payload = fn () => $this->post('/api/v1/memos', [
            'file' => UploadedFile::fake()->create('note.m4a', 5, 'audio/mp4'),
        ], [
            'X-Tenant' => 'RATE1234',
            'Accept' => 'application/json',
        ]);

        $payload()->assertCreated();
        $payload()->assertCreated();
        $payload()->assertTooManyRequests();
    }

    public function test_active_stable_lookup_returns_the_name_and_code(): void
    {
        Stable::factory()->create([
            'name' => 'North Barn',
            'tenant_code' => 'A1B2C3D4',
        ]);

        $this->getJson('/api/v1/stables/a1b2c3d4')
            ->assertOk()
            ->assertExactJson([
                'name' => 'North Barn',
                'tenant_code' => 'A1B2C3D4',
                'language' => 'en',
            ]);
    }

    public function test_unknown_and_inactive_stable_lookups_match(): void
    {
        Stable::factory()->inactive()->create([
            'name' => 'Closed Barn',
            'tenant_code' => 'DEADBEEF',
        ]);

        $unknown = $this->getJson('/api/v1/stables/NOPE1234');
        $inactive = $this->getJson('/api/v1/stables/DEADBEEF');

        $unknown->assertNotFound();
        $inactive->assertNotFound();
        $this->assertSame($unknown->json(), $inactive->json());
        $this->assertArrayNotHasKey('name', $unknown->json());
    }

    public function test_stable_lookups_are_rate_limited_by_ip(): void
    {
        config(['stt.rate_limit_per_minute' => 2]);

        $this->getJson('/api/v1/stables/NOPE1234')->assertNotFound();
        $this->getJson('/api/v1/stables/NOPE1234')->assertNotFound();
        $this->getJson('/api/v1/stables/NOPE1234')->assertTooManyRequests();
    }
}
