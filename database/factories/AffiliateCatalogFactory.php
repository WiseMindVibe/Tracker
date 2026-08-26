<?php

namespace Database\Factories;

use App\Models\AffiliateCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateCatalog>
 */
class AffiliateCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        return [
            'name' => $name,
            'slug' => str_replace("", "_", strtolower($name)),
            'shortcut' => null,
            'affiliate_token' => fake()->windowsPlatformToken(),
            'offer_mode' => fake()->randomElement(['static', 'dynamic']),
            'commission_mode' => fake()->randomElement(['delta', 'absolute']),
            'merchant_id_label' => fake()->userName(),
            'blog_redirect_rate' => fake()->numberBetween(0, 100),
        ];
    }
}
