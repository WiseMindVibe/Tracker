<?php

namespace Database\Factories;

use App\Models\TrafficAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficAccount>
 */
class TrafficAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //'company_id' => Company::query()->inRandomOrder()->value('id'),
            //'traffic_catalog_id' => TrafficCatalog::query()->inRandomOrder()->value('id'),
            'status' => fake()->randomElement([
                'active', 'inactive'
            ]),
        ];
    }
}
