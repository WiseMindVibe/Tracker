<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Click;
use App\Models\Company;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects a postback for an unknown click', function (): void {
    AffiliateCatalog::factory()->create([
        'name' => 'YieldKit',
        'slug' => 'yieldkit',
        'commission_mode' => 'absolute',
    ]);

    $response = $this->postJson('http://localhost/postback/yieldkit', [
        'EVENT_ID' => 'event-1',
        'COMMISSION_ID' => 'commission-1',
        'COMMISSION' => '4.25',
        'SUB_ID' => 'missing-click',
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'OPEN',
    ]);

    $response->assertNotFound()
        ->assertJson(['message' => 'Unknown click.']);
});

it('persists a unique postback and acknowledges a duplicate', function (): void {
    $company = Company::factory()->create();
    $catalog = AffiliateCatalog::factory()->create([
        'name' => 'YieldKit',
        'slug' => 'yieldkit',
        'commission_mode' => 'absolute',
    ]);
    $account = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $catalog->id,
    ]);
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $trafficCatalogId = DB::table('traffic_catalog')->insertGetId([
        'name' => 'Test Traffic',
        'slug' => 'test-traffic',
    ]);
    $trafficAccountId = DB::table('traffic_accounts')->insertGetId([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalogId,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $campaignId = DB::table('campaigns')->insertGetId([
        'uuid' => Str::uuid(),
        'traffic_account_id' => $trafficAccountId,
        'name' => 'Test Campaign',
        'country' => 'US',
        'is_tester' => false,
        'fallback_url' => null,
        'status' => 'active',
        'impressions' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $trafficCampaignId = DB::table('campaigns_traffic_ids')->insertGetId([
        'campaign_id' => $campaignId,
        'traffic_campaign_id' => 'test-traffic-id',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $offer = Offer::factory()->create([
        'affiliate_account_id' => $account->id,
        'blog_id' => $blog->id,
    ]);
    $click = Click::factory()->create([
        'click_id' => Str::uuid(),
        'offer_id' => $offer->id,
        'campaign_id' => $campaignId,
        'traffic_campaign_id' => $trafficCampaignId,
    ]);
    $payload = [
        'EVENT_ID' => 'yield-event-1',
        'COMMISSION_ID' => 'yield-commission-1',
        'COMMISSION' => '4.25',
        'SUB_ID' => $click->click_id,
        'EVENT_TYPE' => 'NEW',
        'STATE' => 'OPEN',
        'MODIFIED_DATE' => '2026-09-04T12:00:00Z',
    ];

    $first = $this->postJson('http://localhost/postback/yieldkit', $payload);
    $second = $this->postJson('http://localhost/postback/yieldkit', $payload);

    $first->assertOk()->assertJson(['duplicate' => false]);
    $second->assertOk()->assertJson(['duplicate' => true]);
    $this->assertDatabaseCount('conversions_events', 1);
    $this->assertDatabaseCount('conversions', 1);
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('conversions', [
        'commission_id' => 'yield-commission-1',
        'commission' => '4.25000',
        'status' => 'OPEN',
    ]);
});
