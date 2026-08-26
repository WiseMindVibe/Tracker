<?php

namespace Database\Factories;

use App\Models\CampaignTrafficId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignTrafficId>
 */
class CampaignTrafficIdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //campaign_id
            'traffic_campaign_id' => fake()->phoneNumber(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
