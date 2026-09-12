<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Campaign;
use App\Models\CampaignTrafficId;
use App\Models\Click;
use App\Models\Company;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;

/**
 * @return array{user: User, offer: Offer, campaign: Campaign, company: Company}
 */
function reportingFixture(?User $user = null): array
{
    $user ??= User::factory()->create();
    $company = Company::factory()->create();
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $affiliateCatalog = AffiliateCatalog::factory()->create();
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $affiliateCatalog->id,
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
    $campaign = Campaign::factory()->create([
        'traffic_account_id' => $trafficAccount->id,
    ]);
    $trafficCampaignId = CampaignTrafficId::factory()->create([
        'campaign_id' => $campaign->id,
    ]);

    Click::factory()->create([
        'offer_id' => $offer->id,
        'campaign_id' => $campaign->id,
        'traffic_campaign_id' => $trafficCampaignId->id,
        'country' => 'US',
        'cost' => 5.25,
        'created_at' => now(),
    ]);

    return compact('user', 'offer', 'campaign', 'company');
}

test('legacy reports route is retired and the reporting screen is served at /reporting', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $legacyResponse = $this->get('/reports');
    $legacyResponse->assertNotFound();

    $response = $this->get('/reporting');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Reporting/Index')
        ->where('defaultDatePreset', 'last7')
        ->where('defaultGroupBy.0', 'offer')
    );
});

test('report api returns grouped rows for the default last7 range including today', function () {
    ['user' => $user, 'offer' => $offer, 'campaign' => $campaign] = reportingFixture();
    $this->actingAs($user);

    $response = $this->postJson('/reporting/report', [
        'date_preset' => 'last7',
        'group_by' => ['offer', 'campaign'],
        'level' => 0,
        'sort_by' => 'clicks',
        'sort_dir' => 'desc',
        'include_totals' => true,
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'rows' => [
            [
                'group_field',
                'group_name',
                'can_expand',
                'parent_filters',
                'clicks',
                'revenue',
            ],
        ],
        'totals',
        'date_from',
        'date_to',
    ]);
    $this->assertNotEmpty($response->json('rows'));
    $this->assertSame('offer_id', $response->json('rows.0.group_field'));
    $this->assertTrue($response->json('rows.0.can_expand'));
    $this->assertSame($offer->id, $response->json('rows.0.parent_filters.offer_id'));
    $this->assertSame(now()->toDateString(), $response->json('date_to'));

    $childResponse = $this->postJson('/reporting/report', [
        'date_preset' => 'last7',
        'group_by' => ['offer', 'campaign'],
        'level' => 1,
        'parent_filters' => ['offer_id' => $offer->id],
        'sort_by' => 'clicks',
        'sort_dir' => 'desc',
    ]);

    $childResponse->assertOk();
    $this->assertSame('campaign_id', $childResponse->json('rows.0.group_field'));
    $this->assertSame($offer->id, $childResponse->json('rows.0.parent_filters.offer_id'));
    $this->assertSame($campaign->id, $childResponse->json('rows.0.parent_filters.campaign_id'));
});

test('report api scoped to a company does not include other companies clicks', function () {
    ['user' => $user, 'offer' => $offer, 'company' => $company] = reportingFixture();
    $user->companies()->attach($company->id);
    $this->actingAs($user);

    $other = reportingFixture();

    $response = $this->postJson('/reporting/report', [
        'date_preset' => 'last7',
        'group_by' => ['offer'],
        'level' => 0,
        'sort_by' => 'clicks',
        'sort_dir' => 'desc',
    ]);

    $response->assertOk();
    $this->assertSame([$offer->id], collect($response->json('rows'))->pluck('group_key')->all());
    $this->assertNotContains($other['offer']->id, collect($response->json('rows'))->pluck('group_key')->all());
});
