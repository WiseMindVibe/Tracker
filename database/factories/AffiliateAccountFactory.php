<?php

namespace Database\Factories;

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateAccount>
 */
class AffiliateAccountFactory extends Factory
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
            'affiliate_catalog_id' => AffiliateCatalog::query()->inRandomOrder()->value('id'),
            'status' => fake()->randomElement([
                'active',
                'inactive'
            ]),
        ];
    }
}
