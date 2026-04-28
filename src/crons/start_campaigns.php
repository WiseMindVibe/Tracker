<?php
require_once __DIR__ . "/../bootstrap.php";

header('Content-Type: application/json');

$db = db();

echo "Gathering all appropriate campaigns...\n";

// STEP 1: Find campaign-offer rows where the offer can still receive traffic.
$stmt = $db->prepare("
    SELECT
        campaign_id,
        offer_id,
        current_views,
        cap
    FROM campaign_offers
    WHERE cap > current_views
      AND cap > 0
    ORDER BY campaign_id, offer_id
");

$stmt->execute();
$campaignRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// STEP 2: Group rows by campaign and enrich with traffic-source + external IDs.
$campaignsById = [];

foreach ($campaignRows as $campaign) {
    $cid = $campaign['campaign_id'];

    if (!isset($campaignsById[$cid])) {
        $stmt = $db->prepare("
        SELECT t.id, t.name, t.api_key
        FROM campaigns c
        LEFT JOIN traffic_sources t ON c.traffic_source_id = t.id
        WHERE c.id = :cid
        ");
        $stmt->execute(['cid' => $cid]);
        $ts = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT external_campaign_id
            FROM campaign_external_ids
            WHERE campaign_id = :cid
        ");
        $stmt->execute(['cid' => $cid]);
        $externalIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $normalizedExternalIds = [];
        foreach ($externalIds as $externalId) {
            if ($externalId === null || $externalId === '') {
                continue;
            }
            $normalizedExternalIds[] = (int) $externalId;
        }
        $normalizedExternalIds = array_values(array_unique($normalizedExternalIds));

        $campaignsById[$cid] = [
            "campaign_id" => (int) $cid,
            "traffic_source_id" => isset($ts['id']) ? (int) $ts['id'] : null,
            "traffic_source" => $ts['name'] ?? null,
            "traffic_source_api_key" => $ts['api_key'] ?? null,
            "external_campaign_ids" => $normalizedExternalIds,
            "offers" => []
        ];
    }

    $campaignsById[$cid]['offers'][] = [
        "offer_id" => (int) $campaign['offer_id'],
        "current_views" => (int) $campaign['current_views'],
        "cap" => (int) $campaign['cap']
    ];
}

// STEP 3: Send /play calls for PropellerAds campaigns.
$playResults = [];
foreach ($campaignsById as $campaignData) {
    $trafficSourceName = strtolower(trim((string) ($campaignData['traffic_source'] ?? '')));
    
    if (empty($campaignData['external_campaign_ids'])) {
        $playResults[] = [
            'campaign_id' => $campaignData['campaign_id'],
            'traffic_source' => $campaignData['traffic_source'],
            'success' => false,
            'reason' => 'no_external_campaign_ids',
        ];
        continue;
    }
    if (empty($campaignData['traffic_source_api_key'])) {
        $playResults[] = [
            'campaign_id' => $campaignData['campaign_id'],
            'traffic_source' => $campaignData['traffic_source'],
            'success' => false,
            'reason' => 'missing_api_key',
        ];
        continue;
    }

    switch($trafficSourceName){
        case 'propellerads':
            $apiUrl = "https://ssp-api.propellerads.com/v5/adv/campaigns/play";
            $payload = ['campaign_ids' => $campaignData['external_campaign_ids']];
        
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $campaignData['traffic_source_api_key'],
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($ch) ? curl_error($ch) : null;
            curl_close($ch);

            $playResults[] = [
                'campaign_id' => $campaignData['campaign_id'],
                'traffic_source' => $campaignData['traffic_source'],
                'external_campaign_ids' => $campaignData['external_campaign_ids'],
                'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response' => $response,
            ];
            break;
            //HilltopAds is a straight API request with parameters
        case 'hilltopads':
            $apiUrl = "https://api.hilltopads.com/advertiser/startCampaign"
            . "?key=" .$campaignData['traffic_source_api_key'];

            $campaignList = null;
            foreach($campaignData['external_campaign_ids'] as $campaignId){
                $campaignList .= "&campaignID=$campaignId";
            }
            $apiUrl .= $campaignList;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($ch) ? curl_error($ch) : null;
            curl_close($ch);

            $playResults[] = [
                'campaign_id' => $campaignData['campaign_id'],
                'traffic_source' => $campaignData['traffic_source'],
                'external_campaign_ids' => $campaignData['external_campaign_ids'],
                'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
                'response' => $response,
            ];
            break;
        case 'popcash':
            $apiUrl = "https://api.popcash.net";
            break;
        }



}

echo json_encode([
    'ok' => true,
    'campaigns_found' => count($campaignsById),
    'propellerads_calls_attempted' => count($playResults),
    'campaigns' => array_values($campaignsById),
    'propellerads_play_results' => $playResults,
], JSON_PRETTY_PRINT);
