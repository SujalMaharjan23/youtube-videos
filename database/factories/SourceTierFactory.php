<?php

namespace Database\Factories;

use App\Models\SourceTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SourceTier>
 */
class SourceTierFactory extends Factory
{   
    protected $model = SourceTier::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'slug' => $this->faker->slug,
            'interval_value' => $this->faker->numberBetween(1, 100),
        ];
    }
}
