<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Offer;
use App\Models\Click;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedirectController extends Controller
{
    public function handle(Request $request)
    {
        $campaignUuid = $request->query('campaign_uuid');

        $campaign = $campaignUuid
            ? Campaign::where('uuid', $campaignUuid)->where('status', 'active')->first()
            : null;

        if (!$campaign) {
            abort(404);
        }

        // Match the network's campaign_id macro against our known traffic IDs (attribution only)
        $trafficCampaignId = null;
        if ($request->filled('campaign_id')) {
            $trafficCampaignId = $campaign->trafficIds()
                ->where('traffic_campaign_id', $request->query('campaign_id'))
                ->value('id');
        }
        $offer = $this->pickEligibleOffer($campaign);

        $click = new Click([
            'click_id' => (string) \Illuminate\Support\Str::uuid(),
            'campaign_id' => $campaign->id,
            'offer_id' => $offer?->id,
            'traffic_campaign_id' => $trafficCampaignId,
            'country' => $request->query('country'),
            'region' => $request->query('region'),
            'language' => $request->query('language'),
            'device' => $request->query('device'),
            'os_version' => $request->query('osversion'),
            'browser' => $request->query('browser'),
            'browser_version' => $request->query('browser_version'),
            'connection_type' => $request->query('connection_type'),
            'carrier' => $request->query('carrier'),
            'isp' => $request->query('isp'),
            'zoneid' => $request->query('zoneid'),
            'subzone_id' => $request->query('subzone_id'),
            'cost' => $request->query('cost'),
            'user_activity' => $request->query('user_activity'),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'raw_params' => $request->query(),
        ]);


        if (!$offer) {
            $click->status = 'no_offer';
            $click->save();

            return $campaign->fallback_url
                ? redirect()->away($campaign->fallback_url)
                : response()->noContent();
        }

        // Increment view + write the click atomically so a race between two
        // simultaneous clicks can't both slip in under the cap.
        DB::transaction(function () use ($campaign, $offer, $click) {
            $updated = DB::table('campaigns_offers')
                ->where('campaign_id', $campaign->id)
                ->where('offer_id', $offer->id)
                ->where('current_views', '<', DB::raw('cap_views'))
                ->increment('current_views');

            $click->offer_id = $offer->id;
            $click->routed_via = 'direct'; // hardcoded until Phase 2
            $click->status = 'redirected';
            $click->save();
        });

        $affiliateAccount = $offer->affiliateAccount; // belongsTo
        $redirectRate = $affiliateAccount->affiliateCatalog->blog_redirect_rate ?? 0;

        $buffer = $offer->blog->buffers()->inRandomOrder()->first();

        $useBufferPath = $buffer && (mt_rand(1, 100) <= $redirectRate);

        $click->routed_via = $useBufferPath ? 'buffer' : 'direct';
        $click->save();

        if (!$useBufferPath) {
            return redirect()->away($offer->affiliate_link);
        }

        return response()->json([
            'message' => 'Click received',
            'click_id' => $click->click_id,
            'query' => $request->query(),
        ]);

        return redirect()->away(
            route('blog.enter', ['domain' => $offer->blog->domain, 'clickId' => $click->id])
        );
    }

    private function pickEligibleOffer(Campaign $campaign)
    {
        return $campaign->offers()
            ->join('offers', 'offers.id', '=', 'campaigns_offers.offer_id')
            ->where('offers.status', 'active')
            ->where('offers.country', $campaign->country)
            ->whereColumn(
                'campaigns_offers.current_views',
                '<',
                'campaigns_offers.cap_views'
            )
            ->inRandomOrder()
            ->select('offers.*')
            ->first();
    }
}
