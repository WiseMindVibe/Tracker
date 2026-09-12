<?php

namespace App\Services\Traffics;

use App\Models\Campaign;
use App\Services\Traffics\HilltopAds\HilltopAdsClient;
use App\Services\Traffics\PopCash\PopCashClient;
use App\Services\Traffics\PropellerAds\PropellerAdsClient;

class TrafficSourceCampaignCloser
{
    public function close(Campaign $campaign): void
    {
        $trafficCampaignIds = $campaign->trafficIds()
            ->pluck('traffic_campaign_id');

        $trafficSlug = $campaign->trafficAccount
            ->trafficCatalog
            ->slug;

        $credentialKeyName = config("traffic_sources.{$trafficSlug}.key");

        $apiKey = $campaign->trafficAccount->trafficCredentials
            ->where('key', $credentialKeyName)
            ->first()?->value;

        $result = match ($trafficSlug) {
            'propellerads' => (new PropellerAdsClient)->stopCampaigns($trafficCampaignIds, $apiKey),
            'hilltopads' => (new HilltopAdsClient)->stopCampaigns($trafficCampaignIds, $apiKey),
            'popcash' => (new PopCashClient)->stopCampaigns($trafficCampaignIds, $apiKey),

            default => ['success' => false, 'message' => "No client configured for {$trafficSlug}"],
        };

        // dd($result);
    }
}
