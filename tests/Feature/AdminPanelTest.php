<?php

namespace Tests\Feature;

use App\Ai\Agents\HorseLogAgent;
use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Filament\Auth\RegisterStable;
use App\Filament\Resources\Memos\Pages\ViewMemo;
use App\Filament\Resources\StableMates\Pages\CreateStableMate;
use App\Models\Memo;
use App\Models\Stable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Transcription;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_login_screen_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('sign up for an account');
    }

    public function test_registration_creates_an_owner_and_a_stable(): void
    {
        Livewire::test(RegisterStable::class)
            ->fillForm([
                'name' => 'Ada Owner',
                'email' => 'ada@stable.test',
                'stable_name' => 'North Barn',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'ada@stable.test')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_super_admin);
        $this->assertSame(UserRole::Owner, $user->role);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertAuthenticatedAs($user);

        $stable = $user->stable;
        $this->assertNotNull($stable);
        $this->assertSame('North Barn', $stable->name);
        $this->assertTrue($stable->is_active);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $stable->tenant_code);
        $this->assertSame(1, Stable::query()->count());
        $this->assertSame(1, User::query()->count());

        $this->get('/admin/'.$stable->tenant_code)
            ->assertOk()
            ->assertSee('North Barn')
            ->assertSee($stable->tenant_code);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        $stable = Stable::factory()->create();
        User::factory()->for($stable)->create(['email' => 'ada@stable.test']);

        Livewire::test(RegisterStable::class)
            ->fillForm([
                'name' => 'Ada Owner',
                'email' => 'ada@stable.test',
                'stable_name' => 'North Barn',
                'password' => 'password',
                'passwordConfirmation' => 'password',
            ])
            ->call('register')
            ->assertHasFormErrors(['email']);

        $this->assertSame(1, Stable::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertGuest();
    }

    public function test_owner_dashboard_is_scoped_to_their_stable(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4', 'name' => 'Demo Stable']);
        $other = Stable::factory()->create(['name' => 'Other Stable']);
        $owner = User::factory()->for($stable)->owner()->create();

        Memo::factory()->for($stable)->done()->create([
            'transcript' => 'North paddock gate',
        ]);
        Memo::factory()->for($other)->done()->create([
            'transcript' => 'SECRET OTHER STABLE',
        ]);

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4')
            ->assertOk()
            ->assertSee('Memos today')
            ->assertSee('North paddock gate')
            ->assertSee('data:image/svg+xml;base64', false)
            ->assertDontSee('SECRET OTHER STABLE');

        $this->actingAs($owner)
            ->get('/admin/'.$other->tenant_code)
            ->assertNotFound();
    }

    public function test_owner_can_open_stable_settings_and_a_member_cannot(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();
        $member = User::factory()->for($stable)->create();

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/profile')
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64', false)
            ->assertSee('A1B2C3D4');
        $this->actingAs($member)->get('/admin/A1B2C3D4/profile')->assertNotFound();
    }

    public function test_super_admin_can_list_every_stable_and_a_member_cannot(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4', 'name' => 'Demo Stable']);
        Stable::factory()->create(['name' => 'Hill Barn']);
        $super = User::factory()->for($stable)->superAdmin()->create();
        $member = User::factory()->for($stable)->create();

        $this->actingAs($super)
            ->get('/admin/A1B2C3D4/stables')
            ->assertOk()
            ->assertSee('Demo Stable')
            ->assertSee('Hill Barn');

        $this->actingAs($member)
            ->get('/admin/A1B2C3D4/stables')
            ->assertForbidden();
    }

    public function test_owner_can_invite_a_stable_mate(): void
    {
        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($stable);

        Livewire::actingAs($owner)
            ->test(CreateStableMate::class)
            ->fillForm([
                'name' => 'Sam Rider',
                'email' => 'sam@stable.test',
                'role' => UserRole::Member->value,
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'sam@stable.test',
            'stable_id' => $stable->id,
            'role' => UserRole::Member->value,
        ]);
    }

    public function test_owner_can_open_a_memo_and_play_audio(): void
    {
        Storage::fake('memos');

        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();
        $memo = Memo::factory()->for($stable)->done()->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'transcript' => 'North paddock gate',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'audio-bytes');

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4/memos/'.$memo->id)
            ->assertOk()
            ->assertSee('North paddock gate')
            ->assertSee('Groq key: set, starts with test, 8 characters.')
            ->assertSee('Provider: groq. Model: whisper-large-v3-turbo.')
            ->assertSee('<audio', false);
    }

    public function test_dashboard_reports_a_database_queue_with_no_worker(): void
    {
        config(['queue.default' => 'database']);

        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);

        $this->actingAs($owner)
            ->get('/admin/A1B2C3D4')
            ->assertOk()
            ->assertSee('Queue: database. 1 ready, 0 in progress, 0 delayed, 0 in failed_jobs.')
            ->assertSee('No queue worker is running.');
    }

    public function test_run_now_writes_the_transcript_on_the_memo(): void
    {
        Storage::fake('memos');
        Transcription::fake(['Turn out the mare.']);
        HorseLogAgent::fake([['horses' => []]]);

        $stable = Stable::factory()->create(['tenant_code' => 'A1B2C3D4']);
        $owner = User::factory()->for($stable)->owner()->create();
        $memo = Memo::factory()->for($stable)->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'status' => MemoStatus::Failed,
            'error' => 'STT_API_KEY is empty in this process.',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'fake-audio');

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($stable);

        Livewire::actingAs($owner)
            ->test(ViewMemo::class, ['record' => $memo->getRouteKey()])
            ->callAction('runNow')
            ->assertNotified('Transcript saved');

        $memo->refresh();
        $this->assertSame(MemoStatus::Done, $memo->status);
        $this->assertSame('Turn out the mare.', $memo->transcript);
        $this->assertNull($memo->error);
    }

    public function test_audio_route_is_limited_to_the_stable(): void
    {
        Storage::fake('memos');

        $stable = Stable::factory()->create();
        $other = Stable::factory()->create();
        $owner = User::factory()->for($stable)->owner()->create();
        $stranger = User::factory()->for($other)->owner()->create();
        $memo = Memo::factory()->for($stable)->create([
            'disk' => 'memos',
            'disk_path' => 'stables/'.$stable->id.'/note.m4a',
            'mime' => 'audio/mp4',
        ]);
        Storage::disk('memos')->put($memo->disk_path, 'audio-bytes');

        $this->get(route('memos.audio', $memo))->assertRedirect('/admin/login');

        $this->actingAs($stranger)
            ->get(route('memos.audio', $memo))
            ->assertForbidden();

        $response = $this->actingAs($owner)->get(route('memos.audio', $memo));
        $response->assertOk();
        $this->assertStringContainsString('audio-bytes', $response->streamedContent());
    }
}
