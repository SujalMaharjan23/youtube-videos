<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_name' => $this->faker->unique()->company,
            'channel_id' => 'UC' . $this->faker->unique()->regexify('[A-Za-z0-9]{10}'),
            'username' => $this->faker->unique()->userName,
            'description' => $this->faker->sentence,
            'channel_logo_url' => $this->faker->url,
            'hidden' => $this->faker->boolean,
        ];
    }
}
