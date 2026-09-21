<?php

namespace Database\Factories;

use App\Models\TrafficFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrafficFieldDefinition>
 */
class TrafficFieldDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fields = [
            ['label' => 'API Key',      'field_key' => 'api_key'],
            ['label' => 'API Secret',   'field_key' => 'api_secret'],
            ['label' => 'Publisher ID', 'field_key' => 'publisher_id'],
            ['label' => 'Access Token', 'field_key' => 'access_token'],
            ['label' => 'Client ID',    'field_key' => 'client_id'],
        ];

        $field = fake()->randomElement($fields);

        return [
            //'traffic_catalog_id' => TrafficCatalog::query()->inRandomOrder()->value('id'),
            'label' => $field['label'],
            'field_key' => $field['field_key'],
        ];
    }
}
