<?php

namespace Database\Factories;

use App\Models\Conversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversion>
 */
class ConversionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //click_id
            //'commission' => fake()->randomDigit(),
            //'status' => fake()->randomElement(['Open', 'Confirmed', 'Rejected', 'Paid']),
        ];
    }
}
