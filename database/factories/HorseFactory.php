<?php

namespace Database\Factories;

use App\Models\Horse;
use App\Models\Stable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Horse>
 */
class HorseFactory extends Factory
{
    protected $model = Horse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stable_id' => Stable::factory(),
            'name' => fake()->firstName(),
            'aliases' => [],
            'knowledge' => '',
        ];
    }
}
