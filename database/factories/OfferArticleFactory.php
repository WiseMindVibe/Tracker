<?php

namespace Database\Factories;

use App\Models\OfferArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferArticle>
 */
class OfferArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //offer_id
            'article_url' => fake()->url(),
        ];
    }
}
