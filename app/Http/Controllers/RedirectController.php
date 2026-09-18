<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Click;
use App\Models\ClicksRedirections;
use App\Services\Traffics\TrafficSourceCampaignCloser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RedirectController extends Controller
{
    public function handle(Request $request)
    {
        $debug = false;

        // Identify valid campaign
        $campaignUuid = $request->query('campaign_uuid');

        $campaign = $campaignUuid
            ? Campaign::where('uuid', $campaignUuid)->where('status', 'active')->first()
            : null;
        if (! $campaign) {
            abort(404);
        }

        // SafeRedirect URL
        $fallbackURL = $campaign->fallback_url;

        if (! $fallbackURL) {
            abort(404, 'Campaign fallback URL is not configured.');
        }

        // Identiy traffic campaign
        $trafficCampaignId = $request->query('campaign_id');

        // Choose a random offer from the campaign BASED ON WEIGHT SELECTION
        $campaignOffer = $this->pickEligibleOffer($campaign);

        if (! $campaignOffer) {
            (new TrafficSourceCampaignCloser)->close($campaign);

            if (! $debug) {
                return $this->safeRedirect($fallbackURL);
            }
        }

        // Check if click's country matchs offer country
        $country = $request->query('country');
        if (strtolower($campaignOffer->offer->country) !== strtolower($country)) {
            if (! $debug) {
                return $this->safeRedirect($fallbackURL);
            } else {
                return 'Campign Country MISMATCH! Got: ' . $country . '. Expected: ' . $campaignOffer->offer->country;
            }
        }

        $cost = $request->query('cost');
        if (!is_numeric($cost)) {
            $cost = 0.0;
        } else {
            $cost = (float) $cost;
        }
        // Receive paramerters from the traffic source
        // & Generate A click_id
        $click = new Click([
            'sub_id' => $request->query('SUB_ID'),
            'click_id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'offer_id' => $campaignOffer?->offer->id,
            'traffic_campaign_id' => $trafficCampaignId,
            'country' => $country,
            'region' => $request->query('region'),
            'language' => $request->query('language'),
            'device' => $request->query('device'),
            'os' => $request->query('os'),
            'os_version' => $request->query('os_version'),
            'browser' => $request->query('browser'),
            'browser_version' => $request->query('browser_version'),
            'connection_type' => $request->query('connection_type'),
            'carrier' => $request->query('carrier'),
            'isp' => $request->query('isp'),
            'zoneid' => $request->query('zoneid'),
            'subzone_id' => $request->query('subzone_id'),
            'cost' => $cost,
            'user_activity' => $request->query('user_activity'),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'raw_params' => $request->query(),
        ]);

        // Depends on affiliate's blog redirect rate
        $blogRedirectRate = (int) $campaignOffer->offer->affiliateAccount->affiliateCatalog->blog_redirect_rate;

        // Send traffic directly to affiliate ( Blog Redirect Rate = 0% )
        if (rand(1, 100) <= $blogRedirectRate) {
            // Redirect through blog
            ClicksRedirections::create([
                'click_id' => $click['click_id'],
                'status' => 'tracker-blog',
            ]);

            // Save Data to clicks
            $click->save();

            // Grab these:
            // - Click ID
            // - Affiliate Link
            // - Affiliate -> Token
            // - Affiliate -> Blog Redirect Rate
            // - Blog -> Domain
            // - Blog -> Buffer URL

            $payload = [
                'click_id' => $click->click_id,
                'affiliate_link' => $campaignOffer->offer->affiliate_link,
                'affiliate_token' => $campaignOffer->offer->affiliateAccount->affiliateCatalog->affiliate_token,
                'domain' => $campaignOffer->offer->blog->domain,
                'buffer_url' => $campaignOffer->offer->blog->buffers->random()->buffer_url,
                'status' => 'tracker-blog',
            ];

            $reference = base64_encode(
                json_encode($payload)
            );

            $blogURL = $campaignOffer->offer->blog->domain . '?ref=' . urlencode($reference);

            $campaignOffer->increment('current_views');

            return $this->safeRedirect($blogURL);
        } else {
            // Redirect directly to affiliate
            ClicksRedirections::create([
                'click_id' => $click['click_id'],
                'status' => 'direct',
            ]);

            $campaignOffer->increment('current_views');

            // Save Data to clicks
            $click->save();

            return $this->safeRedirect($campaignOffer->offer->affiliate_link);
        }
    }

    // Choose a random offer from the campaign BASED ON WEIGHT SELECTION
    // // ~ If campaign's cap reached -> stop request to traffic campaign id & send the click to a fallback URL
    private function pickEligibleOffer(Campaign $campaign)
    {
        // / - Check if cap > current views
        $campaignOffers = $campaign->offers()
            ->whereColumn('cap_views', '>', 'current_views')
            ->with('offer')
            ->get()
            // / - Check if offer is active
            ->filter(fn($campaignOffer) => $campaignOffer->offer?->status === 'active');

        if ($campaignOffers->isEmpty()) {
            return null;
        }

        $lowestFillPercentage = $campaignOffers
            ->map(function ($campaignOffer) {
                return $campaignOffer->cap_views > 0
                    ? $campaignOffer->current_views / $campaignOffer->cap_views
                    : 1;
            })
            ->min();

        $lowestOffers = $campaignOffers->filter(function ($campaignOffer) use ($lowestFillPercentage) {
            $fillPercentage = $campaignOffer->cap_views > 0
                ? $campaignOffer->current_views / $campaignOffer->cap_views
                : 1;

            return $fillPercentage === $lowestFillPercentage;
        });

        return $lowestOffers->random();
    }

    private function safeRedirect(string $url): RedirectResponse
    {
        return redirect()->away($url);
    }
}
