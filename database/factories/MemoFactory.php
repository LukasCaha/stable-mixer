<?php

namespace Database\Factories;

use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use App\Models\Memo;
use App\Models\Stable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memo>
 */
class MemoFactory extends Factory
{
    protected $model = Memo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stable_id' => Stable::factory(),
            'disk' => 'memos',
            'disk_path' => 'stables/example/'.fake()->uuid().'.m4a',
            'mime' => 'audio/mp4',
            'size' => 1024,
            'status' => MemoStatus::Queued,
            'transcript' => null,
            'error' => null,
            'log_status' => HorseLogStatus::Pending,
            'log_error' => null,
            'recorded_at' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => MemoStatus::Done,
            'transcript' => 'Fed the horses and checked the north paddock gate.',
        ]);
    }
}
