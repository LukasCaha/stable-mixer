<?php

namespace Database\Seeders;

use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Models\Memo;
use App\Models\Stable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $stable = Stable::query()->create([
            'name' => 'Demo Stable',
            'tenant_code' => 'A1B2C3D4',
            'is_active' => true,
        ]);

        User::query()->create([
            'stable_id' => $stable->id,
            'name' => 'Demo Owner',
            'email' => 'owner@stable-mixer.test',
            'password' => 'password',
            'role' => UserRole::Owner,
            'is_super_admin' => true,
        ]);

        $disk = (string) config('memos.disk');
        $path = 'stables/'.$stable->id.'/sample.wav';
        Storage::disk($disk)->put($path, $this->wavSilence());

        Memo::query()->create([
            'stable_id' => $stable->id,
            'disk' => $disk,
            'disk_path' => $path,
            'mime' => 'audio/wav',
            'size' => Storage::disk($disk)->size($path),
            'status' => MemoStatus::Done,
            'transcript' => 'Fed the horses and checked the north paddock gate.',
            'recorded_at' => now(),
        ]);
    }

    private function wavSilence(): string
    {
        $sampleRate = 8000;
        $data = str_repeat(pack('v', 0), $sampleRate);
        $dataLength = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataLength)
            .'WAVE'
            .'fmt '
            .pack('V', 16)
            .pack('v', 1)
            .pack('v', 1)
            .pack('V', $sampleRate)
            .pack('V', $sampleRate * 2)
            .pack('v', 2)
            .pack('v', 16)
            .'data'
            .pack('V', $dataLength)
            .$data;
    }
}
