<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignTrafficId;
use App\Models\Click;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Click>
 */
class ClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'click_id' => 'clk_' . fake()->unique()->bothify('##########'),
            'offer_id' => Offer::query()->inRandomOrder()->value('id'),
            'campaign_id' => Campaign::query()->inRandomOrder()->value('id'),
            'traffic_campaign_id' => CampaignTrafficId::query()->inRandomOrder()->value('id'),

            'routed_via' => 'direct',
            'status' => 'pending',
            'is_bot' => false,
            'country' => fake()->countryCode(),
            'region' => fake()->state(),
            'language' => fake()->randomElement(['en', 'es', 'fr', 'de', 'it', 'pt', 'ar', 'ru']),

            'device' => fake()->randomElement(['desktop', 'mobile', 'tablet']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
            'os_version' => fake()->randomElement([
                '10',
                '11',
                '12',
                '13',
                '14',
                '15',
                '16',
                '17',
                '18'
            ]),

            'browser' => fake()->randomElement([
                'Chrome',
                'Firefox',
                'Edge',
                'Safari',
                'Opera'
            ]),
            'browser_version' => fake()->numberBetween(80, 140),

            'connection_type' => fake()->randomElement([
                'wifi',
                '4g',
                '5g',
                'ethernet'
            ]),
            'isp' => fake()->company(),
            'carrier' => fake()->randomElement([
                'Verizon',
                'AT&T',
                'T-Mobile',
                'Vodafone',
                'Orange',
                'None'
            ]),

            'zoneid' => fake()->numberBetween(1, 1000),
            'subzone_id' => fake()->numberBetween(1, 1000),

            'user_agent' => fake()->userAgent(),
            'user_activity' => fake()->randomElement([
                'low',
                'medium',
                'high',
            ]),

            'ip_address' => fake()->ipv4(),
            'cost' => fake()->randomFloat(4, 0, 5),
        ];
    }
}
