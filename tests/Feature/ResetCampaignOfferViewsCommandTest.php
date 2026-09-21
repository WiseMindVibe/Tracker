<?php

use App\Models\AffiliateAccount;
use App\Models\AffiliateCatalog;
use App\Models\Blog;
use App\Models\Campaign;
use App\Models\CampaignOffer;
use App\Models\Company;
use App\Models\Offer;
use App\Models\TrafficAccount;
use App\Models\TrafficCatalog;
use Illuminate\Support\Facades\DB;

it('resets current views for every campaign offer', function () {
    $company = Company::factory()->create();
    $trafficCatalog = TrafficCatalog::factory()->create();
    $trafficAccount = TrafficAccount::factory()->create([
        'company_id' => $company->id,
        'traffic_catalog_id' => $trafficCatalog->id,
    ]);
    $campaign = Campaign::factory()->create(['traffic_account_id' => $trafficAccount->id]);

    $affiliateCatalog = AffiliateCatalog::factory()->create();
    $affiliateAccount = AffiliateAccount::factory()->create([
        'company_id' => $company->id,
        'affiliate_catalog_id' => $affiliateCatalog->id,
    ]);
    $blog = Blog::factory()->create(['company_id' => $company->id]);
    $offer = Offer::factory()->create([
        'affiliate_account_id' => $affiliateAccount->id,
        'blog_id' => $blog->id,
    ]);

    CampaignOffer::factory()->create([
        'campaign_id' => $campaign->id,
        'offer_id' => $offer->id,
        'current_views' => 12,
        'total_views' => 30,
    ]);

    $this->artisan('campaigns:reset-current-views')
        ->expectsOutput('Reset current views for 1 campaign offers.')
        ->assertExitCode(0);

    expect(CampaignOffer::query()->value('current_views'))->toBe(0);
    expect(DB::table('campaigns_offers')->pluck('total_views')->all())
        ->toBe([30]);
});
