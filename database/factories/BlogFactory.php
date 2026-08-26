<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<blog>
 */
class BlogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::query()->inRandomOrder()->value('id'),
            'domain' => fake()->domainName(),
            'main_geo' => fake()->countryCode(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
