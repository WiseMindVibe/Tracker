<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Campaign;
use App\Models\CampaignTrafficId;
use App\Models\Click;
use App\Models\Company;
use App\Models\Conversion;
use App\Models\ConversionEvent;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        'traffic_campaign_id' => $trafficCampaignId->traffic_campaign_id,
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
        ->where('defaultDateFrom', now()->subDays(6)->toDateString())
        ->where('defaultDateTo', now()->toDateString())
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

test('all-time reports include clicks from before 2000', function () {
    ['user' => $user, 'offer' => $offer] = reportingFixture();
    $this->actingAs($user);

    Click::factory()->create([
        'offer_id' => $offer->id,
        'created_at' => '1999-12-31 12:00:00',
    ]);

    $response = $this->postJson('/reporting/report', [
        'date_preset' => 'all_time',
        'group_by' => ['offer'],
        'level' => 0,
        'sort_by' => 'clicks',
        'sort_dir' => 'desc',
        'include_totals' => true,
    ]);

    $response->assertOk();
    $this->assertSame(2, $response->json('totals.clicks'));
    $this->assertSame('1000-01-01', $response->json('date_from'));
});

test('report api does not refresh every commission snapshot before building a report', function () {
    ['user' => $user, 'offer' => $offer] = reportingFixture();
    $this->actingAs($user);

    $click = Click::query()->where('offer_id', $offer->id)->firstOrFail();
    $catalog = AffiliateCatalog::factory()->create();
    $eventId = DB::table('conversions_events')->insertGetId([
        'click_id' => $click->id,
        'affiliate_catalog_id' => $catalog->id,
        'commission_id' => 'report-snapshot-test',
        'commission' => 10,
        'status' => 'open',
        'created_at' => now(),
    ]);
    DB::table('conversions')->insert([
        'conversion_event_id' => $eventId,
        'transaction_id' => 'report-snapshot-test',
        'commission_id' => 'report-snapshot-test',
        'commission' => 123,
        'status' => 'open',
        'accumulated_commission' => 123,
        'loss' => 0,
        'events_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->postJson('/reporting/report', [
        'date_preset' => 'last7',
        'group_by' => ['offer'],
        'level' => 0,
        'sort_by' => 'clicks',
        'sort_dir' => 'desc',
    ]);

    $response->assertOk();
    $this->assertSame(
        123.0,
        (float) DB::table('conversions')->where('transaction_id', 'report-snapshot-test')->value('commission'),
    );
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

test('inspect page lists only offers available to the signed-in user', function () {
    ['user' => $user, 'offer' => $offer, 'company' => $company] = reportingFixture();
    $user->companies()->attach($company->id);
    $other = reportingFixture();
    $this->actingAs($user);

    $response = $this->get('/inspect');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Inspect/Index')
        ->where('selectedOfferId', $offer->id)
        ->where('offers.0.id', $offer->id)
        ->where('analyticsEndpoint', route('inspect.analytics'))
    );
    $this->assertNotContains($other['offer']->id, collect($response->viewData('page')['props']['offers'])->pluck('id')->all());
});

test('inspect analytics compares periods and reports conversion timing and commission highs and lows', function () {
    ['user' => $user, 'offer' => $offer] = reportingFixture();
    $this->actingAs($user);

    $catalog = AffiliateCatalog::factory()->create(['name' => 'Inspection network']);
    $click = Click::query()->where('offer_id', $offer->id)->firstOrFail();
    $openedAt = now()->subHours(2);
    $confirmedAt = $openedAt->copy()->addHour();
    $paidAt = $confirmedAt->copy()->addMinutes(30);

    $paidEvent = null;
    foreach ([
        ['commission_id' => 'paid-order', 'status' => 'OPEN', 'at' => $openedAt],
        ['commission_id' => 'paid-order', 'status' => 'CONFIRMED', 'at' => $confirmedAt],
        ['commission_id' => 'paid-order', 'status' => 'PAID', 'at' => $paidAt],
    ] as $eventData) {
        $paidEvent = ConversionEvent::factory()->create([
            'click_id' => $click->id,
            'affiliate_catalog_id' => $catalog->id,
            'commission_id' => $eventData['commission_id'],
            'status' => $eventData['status'],
            'commission' => 12,
            'created_at' => $eventData['at'],
        ]);
    }
    Conversion::factory()->create([
        'conversion_event_id' => $paidEvent->id,
        'transaction_id' => 'inspection-paid-order',
        'commission_id' => 'paid-order',
        'commission' => 12,
        'status' => 'paid',
    ]);

    $openEvent = ConversionEvent::factory()->create([
        'click_id' => $click->id,
        'affiliate_catalog_id' => $catalog->id,
        'commission_id' => 'open-order',
        'status' => 'OPEN',
        'commission' => 2,
        'created_at' => $openedAt,
    ]);
    Conversion::factory()->create([
        'conversion_event_id' => $openEvent->id,
        'transaction_id' => 'inspection-open-order',
        'commission_id' => 'open-order',
        'commission' => 2,
        'status' => 'open',
    ]);

    ConversionEvent::factory()->create([
        'click_id' => $click->id,
        'affiliate_catalog_id' => $catalog->id,
        'commission_id' => 'rejected-order',
        'status' => 'OPEN',
        'commission' => 1,
        'created_at' => $openedAt->copy()->subHour(),
    ]);
    $rejectedEvent = ConversionEvent::factory()->create([
        'click_id' => $click->id,
        'affiliate_catalog_id' => $catalog->id,
        'commission_id' => 'rejected-order',
        'status' => 'REJECTED',
        'commission' => 1,
        'created_at' => $openedAt->copy()->subMinutes(45),
    ]);
    Conversion::factory()->create([
        'conversion_event_id' => $rejectedEvent->id,
        'transaction_id' => 'inspection-rejected-order',
        'commission_id' => 'rejected-order',
        'commission' => 1,
        'status' => 'rejected',
    ]);

    $response = $this->postJson(route('inspect.analytics'), [
        'offer_id' => $offer->id,
        'date_from' => now()->subDays(29)->toDateString(),
        'date_to' => now()->toDateString(),
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'lifetime' => ['label', 'totals', 'campaigns', 'insights'],
            'this_year' => ['label', 'totals', 'campaigns', 'insights'],
            'last_90_days' => ['label', 'totals', 'campaigns', 'insights'],
            'selected' => ['label', 'totals', 'campaigns', 'insights'],
        ])
        ->assertJsonPath('selected.insights.transitionAverages.openToConfirmedMinutes', 60)
        ->assertJsonPath('selected.insights.transitionAverages.confirmedToPaidMinutes', 30)
        ->assertJsonPath('selected.insights.transitionAverages.samples.openToConfirmed', 1)
        ->assertJsonPath('selected.insights.transitionAverages.openToRejectedMinutes', 15)
        ->assertJsonPath('selected.insights.lowestCommission.amount', 1)
        ->assertJsonPath('selected.insights.highestCommission.amount', 12)
        ->assertJsonPath('selected.insights.days.0.conversions', 3)
        ->assertJsonPath('selected.insights.days.0.revenue', 14)
        ->assertJsonPath('selected.insights.days.0.cr', 300);
});

test('inspect analytics rejects offers outside the signed-in user scope', function () {
    ['user' => $user, 'offer' => $offer, 'company' => $company] = reportingFixture();
    $user->companies()->attach($company->id);
    $other = reportingFixture();
    $this->actingAs($user);

    $this->postJson(route('inspect.analytics'), [
        'offer_id' => $other['offer']->id,
        'date_from' => now()->subDays(29)->toDateString(),
        'date_to' => now()->toDateString(),
    ])->assertNotFound();
});
