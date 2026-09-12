<?php

namespace App\Services\Traffics\HilltopAds;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HilltopAdsClient
{
    public function stopCampaigns(Collection|array $campaignIds, string $apiKey): array
    {
        $url = config('traffic_sources.hilltopads.stop');

        $idsArray = $campaignIds instanceof Collection
            ? $campaignIds->values()->all()
            : array_values($campaignIds);

        $results = [];
        $allSuccessful = true;

        foreach ($idsArray as $campaignId) {
            $response = Http::patch($url.'?'.http_build_query([
                'key' => $apiKey,
                'campaignId' => (int) $campaignId,
            ]));

            $success = $response->successful();
            $allSuccessful = $allSuccessful && $success;

            $results[] = [
                'campaign_id' => $campaignId,
                'success' => $success,
                'status' => $response->status(),
                'raw' => $response->json() ?? $response->body(),
            ];
        }

        return [
            'success' => $allSuccessful,
            'message' => $allSuccessful
                ? 'HilltopAds confirmed all campaigns stopped.'
                : 'HilltopAds failed to stop one or more campaigns.',
            'results' => $results,
        ];
    }
}
