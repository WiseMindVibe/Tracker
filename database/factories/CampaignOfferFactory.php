<?php

namespace Database\Factories;

use App\Models\CampaignOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignOffer>
 */
class CampaignOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $views = fake()->numberBetween(0, 1000);

        return [
            //campaign id
            //offer id
            'current_views' => $views,
            'cap_views' => fake()->numberBetween(0, 1000) + $views,
        ];
    }
}
