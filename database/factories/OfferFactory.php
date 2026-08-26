<?php

namespace Database\Factories;

use App\Models\AffiliateAccount;
use App\Models\Blog;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blog_id' => Blog::query()->inRandomOrder()->value('id'),
            'affiliate_account_id' => AffiliateAccount::query()->inRandomOrder()->value('id'),
            'name' => fake()->name(),
            'type' => fake()->randomElement(['static', 'dynamic']),
            'merchant_id' => fake()->uuid(),
            'country' => fake()->countryCode(),
            'affiliate_link' => fake()->url(),
            'is_tester' => fake()->boolean(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
