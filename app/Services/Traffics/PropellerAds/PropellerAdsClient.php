<?php

namespace App\Services\Traffics\PropellerAds;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PropellerAdsClient
{
    public function stopCampaigns(Collection|array $campaignIds, string $apiKey): array
    {
        $url = config('traffic_sources.propellerads.stop');

        $idsArray = $campaignIds instanceof Collection
            ? $campaignIds->values()->all()
            : array_values($campaignIds);

        $idsArray = array_map('intval', $idsArray);

        $response = Http::withToken($apiKey)
            ->put($url, [
                'campaign_ids' => $idsArray,
            ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'PropellerAds confirmed campaign stop.',
                'raw' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'message' => 'PropellerAds failed to stop campaign.',
            'status' => $response->status(),
            'raw' => $response->json(),
        ];
    }
}
