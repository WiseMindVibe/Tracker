<?php

namespace Database\Factories;

use App\Models\TrafficCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficCatalog>
 */
class TrafficCatalogFactory extends Factory
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
        ];
    }
}
