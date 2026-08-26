<?php

namespace Database\Factories;

use App\Models\ConversionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversionEvent>
 */
class ConversionEventFactory extends Factory
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
            'commission_id' => fake()->phoneNumber(),
            'commission' => fake()->randomDigit(),
            'status' => fake()->randomElement(['Open', 'Confirmed', 'Rejected', 'Paid']),
            //'conversion_id'
        ];
    }
}
