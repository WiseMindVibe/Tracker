<?php

namespace Database\Factories;

use App\Models\AffiliateAccount;
use App\Models\AffiliateAccountCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateAccountCredential>
 */
class AffiliateAccountCredentialFactory extends Factory
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
            //'affiliate_account_id' => AffiliateAccount::query()->inRandomOrder()->value('id'),
            'label' => $field['label'],
            'key' => $field['field_key'],
            'value' => fake()->sha256(),
        ];
    }
}
