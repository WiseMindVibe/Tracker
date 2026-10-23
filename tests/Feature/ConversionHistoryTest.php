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
use Inertia\Testing\AssertableInertia as Assert;

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

it('loads the 100 most recent conversion clicks with their affiliate names', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $campaign = Campaign::factory()->create(['traffic_account_id' => $trafficAccount->id]);
    $affiliateCatalog = AffiliateCatalog::factory()->create([
        'name' => 'Test affiliate network',
        'slug' => 'test-affiliate-network',
        'commission_mode' => 'absolute',
    ]);
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $affiliateCatalog->id,
    ]);
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $offer = Offer::factory()->create([
        'blog_id' => $blog->id,
        'affiliate_account_id' => $affiliateAccount->id,
    ]);
    $latestClickId = null;

    foreach (range(1, 101) as $number) {
        $click = Click::factory()->create([
            'offer_id' => $offer->id,
            'campaign_id' => $campaign->id,
            'traffic_campaign_id' => null,
        ]);
        $latestClickId = $click->click_id;

        ConversionEvent::factory()->create([
            'click_id' => $click->id,
            'affiliate_catalog_id' => $affiliateCatalog->id,
            'commission_id' => "commission-{$number}",
            'commission' => 2,
            'status' => 'PAID',
            'created_at' => now()->subMinutes(101 - $number),
        ]);
    }

    $this->actingAs($user)
        ->get(route('conversions.index'))
        ->assertInertia(function (Assert $page) use ($latestClickId): void {
            $page->component('Conversions/index')
                ->where('searchMode', 'conversions')
                ->where('perPage', 100)
                ->missing('conversions')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('conversions.data', 100)
                    ->where('conversions.total', 101)
                    ->where('conversions.per_page', 100)
                    ->where('conversions.data.0.click_id', $latestClickId)
                    ->where('conversions.data.0.affiliates.0', 'Test affiliate network')
                    ->where('conversions.data.0.commissions.0.affiliate', 'Test affiliate network')
                );
        });
});

it('groups offer searches by exact offer and expands into that offer conversions', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $campaign = Campaign::factory()->create(['traffic_account_id' => $trafficAccount->id]);
    $affiliateCatalog = AffiliateCatalog::factory()->create([
        'name' => 'Search affiliate',
        'commission_mode' => 'absolute',
    ]);
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $affiliateCatalog->id,
    ]);
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $firstOffer = Offer::factory()->create([
        'blog_id' => $blog->id,
        'affiliate_account_id' => $affiliateAccount->id,
        'name' => 'Searchable offer',
    ]);
    $similarOffer = Offer::factory()->create([
        'blog_id' => $blog->id,
        'affiliate_account_id' => $affiliateAccount->id,
        'name' => 'Searchable offer extra',
    ]);

    foreach ([[$firstOffer, 16], [$similarOffer, 1]] as [$offer, $clickCount]) {
        foreach (range(1, $clickCount) as $number) {
            $click = Click::factory()->create([
                'offer_id' => $offer->id,
                'campaign_id' => $campaign->id,
                'traffic_campaign_id' => null,
            ]);
            ConversionEvent::factory()->create([
                'click_id' => $click->id,
                'affiliate_catalog_id' => $affiliateCatalog->id,
                'commission_id' => "search-{$offer->id}-{$number}",
                'commission' => 3,
                'status' => 'PAID',
                'created_at' => now()->subMinutes($offer->id === $firstOffer->id ? 0 : 1),
            ]);
        }
    }

    $this->actingAs($user)
        ->get(route('conversions.index', ['search' => 'Searchable offer']))
        ->assertInertia(function (Assert $page) use ($firstOffer, $similarOffer): void {
            $page->component('Conversions/index')
                ->where('searchMode', 'offers')
                ->where('conversions', null)
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('offerGroups.data', 2)
                    ->where('offerGroups.data.0.id', $firstOffer->id)
                    ->where('offerGroups.data.0.conversions_count', 16)
                    ->where('offerGroups.data.0.affiliate', 'Search affiliate')
                    ->where('offerGroups.data.1.id', $similarOffer->id)
                    ->where('offerGroups.data.1.conversions_count', 1)
                );
        });

    $this->getJson(route('conversions.offers.show', ['offer' => $firstOffer->id, 'per_page' => 15]))
        ->assertOk()
        ->assertJsonPath('total', 16)
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('data.0.offer', 'Searchable offer')
        ->assertJsonPath('data.0.events_count', 1)
        ->assertJsonPath('data.0.commissions.0.affiliate', 'Search affiliate');

    $this->getJson(route('conversions.offers.show', ['offer' => $firstOffer->id, 'page' => 2, 'per_page' => 15]))
        ->assertOk()
        ->assertJsonPath('current_page', 2)
        ->assertJsonCount(1, 'data');
});
