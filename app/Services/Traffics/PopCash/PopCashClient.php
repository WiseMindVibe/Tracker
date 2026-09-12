<?php

namespace App\Services\Traffics\PopCash;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PopCashClient
{
    private const STATUS_PAUSED = 3;

    public function stopCampaigns(Collection|array $campaignIds, string $apiKey): array
    {
        $baseUrl = config('traffic_sources.popcash.stop');

        $idsArray = $campaignIds instanceof Collection
            ? $campaignIds->values()->all()
            : array_values($campaignIds);

        $results = [];
        $allSuccessful = true;

        foreach ($idsArray as $campaignId) {
            $url = $baseUrl.$campaignId;

            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
            ])->put($url, [
                'status' => self::STATUS_PAUSED,
            ]);

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
                ? 'PopCash confirmed all campaigns paused.'
                : 'PopCash failed to pause one or more campaigns.',
            'results' => $results,
        ];
    }
}
