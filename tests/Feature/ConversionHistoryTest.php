<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Campaign;
use App\Models\CampaignTrafficId;
use App\Models\Click;
use App\Models\Company;
use App\Models\ConversionEvent;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;

it('reports the latest status for each commission in conversion history', function (): void {
    $this->actingAs(User::factory()->create());

    $company = Company::factory()->create();
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $catalog = AffiliateCatalog::factory()->create(['commission_mode' => 'delta']);
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $catalog->id,
    ]);
    $offer = Offer::factory()->create([
        'blog_id' => $blog->id,
        'affiliate_account_id' => $affiliateAccount->id,
    ]);
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $campaign = Campaign::factory()->create(['traffic_account_id' => $trafficAccount->id]);
    $trafficCampaignId = CampaignTrafficId::factory()->create(['campaign_id' => $campaign->id]);
    $click = Click::factory()->create([
        'offer_id' => $offer->id,
        'campaign_id' => $campaign->id,
        'traffic_campaign_id' => $trafficCampaignId->traffic_campaign_id,
    ]);

    foreach ([
        ['commission_id' => 'paid-commission', 'statuses' => ['OPEN', 'PAID']],
        ['commission_id' => 'rejected-commission', 'statuses' => ['OPEN', 'REJECTED']],
        ['commission_id' => 'open-commission', 'statuses' => ['OPEN']],
    ] as $commission) {
        foreach ($commission['statuses'] as $index => $status) {
            ConversionEvent::factory()->create([
                'click_id' => $click->id,
                'affiliate_catalog_id' => $catalog->id,
                'commission_id' => $commission['commission_id'],
                'status' => $status,
                'commission' => 1,
                'created_at' => now()->addMinutes($index),
            ]);
        }
    }

    $response = $this->getJson("/conversions/{$click->id}/history");

    $response->assertOk();
    expect(collect($response->json('data'))
        ->pluck('latest_status', 'commission_id')
        ->all())
        ->toMatchArray([
            'paid-commission' => 'PAID',
            'rejected-commission' => 'REJECTED',
            'open-commission' => 'OPEN',
        ]);
});
