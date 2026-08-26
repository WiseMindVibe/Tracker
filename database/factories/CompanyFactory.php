<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyFactory>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();
        return [
            'name' => $company,
            'slug' => str_replace(" ", "_", strtolower($company)),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
