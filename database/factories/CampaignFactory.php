<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\TrafficAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'traffic_account_id' => TrafficAccount::query()->inRandomOrder()->value('id'),
            'name' => fake()->lastName(),
            'country' => fake()->countryCode(),
            'is_tester' => fake()->boolean(),
            'fallback_url' => 'https://google.com'
        ];
    }
}
