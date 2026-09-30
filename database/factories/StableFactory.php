<?php

namespace Database\Factories;

use App\Models\Stable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stable>
 */
class StableFactory extends Factory
{
    protected $model = Stable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Stable',
            'tenant_code' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
