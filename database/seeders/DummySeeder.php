<?php

namespace Database\Seeders;

use App\Models\AffiliateAccount;
use App\Models\AffiliateAccountCredential;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\BlogBuffer;
use App\Models\Campaign;
use App\Models\CampaignOffer;
use App\Models\CampaignTrafficId;
use App\Models\Click;
use App\Models\Company;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\OfferArticle;
use App\Models\TrafficAccount;
use App\Models\TrafficAccountCredential;
use App\Models\TrafficCatalog;
use Illuminate\Database\Seeder;

class DummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        $companies = Company::query()->get();
        $affiliateCatalogs = AffiliateCatalog::query()->get();
        $trafficCatalogs = TrafficCatalog::query()->get();

        $allOffers = collect();
        $allCampaigns = collect();
        $allCampaignTrafficIds = collect();

        foreach ($companies as $company) {

            $blogs = Blog::factory(2)->create([
                'company_id' => $company->id,
            ]);

            foreach ($blogs as $blog) {

                BlogBuffer::factory(2)->create([
                    'blog_id' => $blog->id
                ]);
            }


            $companyOffers = collect();

            foreach ($affiliateCatalogs as $affiliateCatalog) {

                $affiliateFields = $affiliateCatalog->fieldDefinitions;


                $affiliateAccount = AffiliateAccount::factory()->create([
                    'company_id' => $company->id,
                    'affiliate_catalog_id' => $affiliateCatalog->id
                ]);

                $offers = Offer::factory(30)->create([
                    'affiliate_account_id' => $affiliateAccount->id,
                    'blog_id' => $blogs->random()->id,
                ]);

                $companyOffers = $companyOffers->merge($offers);
                $allOffers = $allOffers->merge($offers);

                foreach ($offers as $offer) {
                    OfferArticle::factory(fake()->numberBetween(0, 2))->create([
                        'offer_id' => $offer->id,
                    ]);
                }


                foreach ($affiliateFields as $affiliateField) {
                    AffiliateAccountCredential::factory()->create([
                        'affiliate_account_id' => $affiliateAccount->id,
                        'label' => $affiliateField->label,
                        'value' => fake()->uuid(),
                    ]);
                }
            }

            foreach ($trafficCatalogs as $trafficCatalog) {

                $trafficFields = $trafficCatalog->fieldDefinitions;

                $trafficAccount = TrafficAccount::factory()->create([
                    'company_id' => $company->id,
                    'traffic_catalog_id' => $trafficCatalog->id
                ]);

                $campaigns = Campaign::factory(20)->create([
                    'traffic_account_id' => $trafficAccount->id,
                ]);

                $allCampaigns = $allCampaigns->merge($campaigns);

                foreach ($campaigns as $campaign) {

                    $selectedOffers = $companyOffers->random(fake()->numberBetween(0, min(3, $companyOffers->count())));

                    foreach ($selectedOffers as $selectedOffer) {
                        CampaignOffer::factory()->create([
                            'campaign_id' => $campaign->id,
                            'offer_id' => $selectedOffer->id,
                        ]);
                    }
                    $campaignsTrafficsIds = CampaignTrafficId::factory(fake()->numberBetween(1, 3))->create([
                        'campaign_id' => $campaign->id,
                    ]);

                    $allCampaignTrafficIds = $allCampaignTrafficIds->merge($campaignsTrafficsIds);
                }

                foreach ($trafficFields as $trafficField) {
                    TrafficAccountCredential::factory()->create([
                        'traffic_account_id' => $trafficAccount->id,
                        'label' => $trafficField->label,
                        'value' => fake()->uuid(),
                    ]);
                }
            }
        }

        $clicks = collect();

        for ($i = 0; $i < 1000; $i++) {

            $click = Click::factory()->make([
                'traffic_campaign_id' => $allCampaignTrafficIds->random()->id,
                'offer_id' => $allOffers->random()->id,
                'campaign_id' => $allCampaigns->random()->id,
                'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
            ]);
            $click->id = $i + 1;
            $click->save();

            $clicks->push($click);
        }

        foreach ($clicks as $click) {

            if (fake()->boolean(35)) {

                $eventCreatedAt = fake()->dateTimeBetween($click->created_at, 'now');

                $affiliateCatalogId = $click->offer
                    ->affiliateAccount
                    ->affiliate_catalog_id;

                $conversionEvent = ConversionEvent::query()->create([
                    'click_id' => $click->id,
                    'affiliate_catalog_id' => $click->offer->affiliateAccount->affiliate_catalog_id,
                    'commission_id' => fake()->unique()->uuid(),
                    'commission' => fake()->randomFloat(5, 0, 100),
                    'status' => fake()->randomElement([
                        'Open',
                        'Confirmed',
                        'Rejected',
                        'Paid',
                    ]),
                    'event_type' => fake()->randomElement([
                        'NEW',
                        'UPDATE',
                    ]),
                    'event_id' => fake()->unique()->uuid(),
                    'created_at' => $eventCreatedAt,
                ]);



                Notification::factory()->create([
                    'conversion_event_id' => $conversionEvent->id,
                    'created_at' => $eventCreatedAt,
                    'updated_at' => $eventCreatedAt
                ]);

                Conversion::factory()->create([
                    'conversion_event_id' => $conversionEvent->id,
                    'transaction_id' => "{$click->offer->affiliateAccount->id}_{$conversionEvent->commission_id}",
                    'created_at' => $eventCreatedAt,
                    'updated_at' => $eventCreatedAt
                ]);
            }
        }
    }
}
