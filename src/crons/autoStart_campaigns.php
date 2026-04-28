<?php
require_once __DIR__ . "/../bootstrap.php";

header('Content-Type: application/json');

$db = db();

$stmt = $db->prepare("SELECT DISTINCT campaign_id
FROM campaign_offers
WHERE cap > 0
AND cap > current_views
ORDER BY campaign_id
");
$stmt->execute();

$campaign_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Divide campaigns by traffic source.
$propellerAdsCampaigns = [];
$hilltopAdsCampaigns = [];
$popcashAdsCampaigns = [];

$propellerKey = null;
$hilltopKey = null;
$popcashKey = null;

$campaignTrafficStmt = $db->prepare("SELECT traffic_source_id
FROM campaigns
WHERE id = :campaign_id
");

$trafficInfoStmt = $db->prepare("SELECT name, api_key
FROM traffic_sources
WHERE id = :id
");

$externalIdsStmt = $db->prepare("SELECT external_campaign_id
FROM campaign_external_ids
WHERE campaign_id = :campaign_id
ORDER BY external_campaign_id DESC
");

foreach($campaign_ids as $campaign){
    $campaignTrafficStmt->execute([':campaign_id' => $campaign['campaign_id']]);
    $trafficSourceId = $campaignTrafficStmt->fetchColumn();

    if ($trafficSourceId === false) {
        continue;
    }

    $trafficInfoStmt->execute([':id' => $trafficSourceId]);
    $trafficInfo = $trafficInfoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$trafficInfo) {
        continue;
    }

    $externalIdsStmt->execute([':campaign_id' => $campaign['campaign_id']]);
    $externalCampaignId = $externalIdsStmt->fetchColumn();

    if ($externalCampaignId === false) {
        continue;
    }

    switch (strtolower((string) $trafficInfo['name'])) {
        case 'propellerads':
            $propellerKey = $trafficInfo['api_key'];
            $propellerAdsCampaigns[] = $externalCampaignId;
            break;
        case 'hilltopads':
            $hilltopKey = $trafficInfo['api_key'];
            $hilltopAdsCampaigns[] = $externalCampaignId;
            break;
        case 'popcash':
            $popcashKey = $trafficInfo['api_key'];
            $popcashAdsCampaigns[] = $externalCampaignId;
            break;
        default:
            break;
    }
}

$apiResults = [
    'propellerads' => null,
    'hilltopads' => null,
    'popcash' => null
];

if (!empty($propellerAdsCampaigns) && !empty($propellerKey)) {
    $apiUrl = "https://ssp-api.propellerads.com/v5/adv/campaigns/play";
    $payload = ['campaign_ids' => $propellerAdsCampaigns];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $propellerKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    $apiResults['propellerads'] = [
        'external_campaign_ids' => $propellerAdsCampaigns,
        'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => $response
    ];
}

if (!empty($hilltopAdsCampaigns) && !empty($hilltopKey)) {
    $hilltopResponses = [];

    foreach ($hilltopAdsCampaigns as $campaignId) {
        $apiUrl = "https://api.hilltopads.com/advertiser/startCampaign?key=" .
            urlencode((string) $hilltopKey) . "&campaignId=" . urlencode((string) $campaignId);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $hilltopResponses[] = [
            'campaign_id' => $campaignId,
            'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response' => $response
        ];
    }

    $apiResults['hilltopads'] = [
        'external_campaign_ids' => $hilltopAdsCampaigns,
        'responses' => $hilltopResponses
    ];
}

if (!empty($popcashAdsCampaigns) && !empty($popcashKey)) {
    $popcashResponses = [];

    foreach ($popcashAdsCampaigns as $campaignId) {
        $apiUrl = "https://api.popcash.net/campaign/" . rawurlencode((string) $campaignId);
        $payload = [
            'status' => 1
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $popcashKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        $popcashResponses[] = [
            'campaign_id' => $campaignId,
            'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'response' => $response
        ];
    }

    $apiResults['popcash'] = [
        'external_campaign_ids' => $popcashAdsCampaigns,
        'responses' => $popcashResponses
    ];
}

echo json_encode([
    'campaign_ids' => $campaign_ids,
    'grouped_campaigns' => [
        'propellerads' => $propellerAdsCampaigns,
        'hilltopads' => $hilltopAdsCampaigns,
        'popcash' => $popcashAdsCampaigns
    ],
    'api_results' => $apiResults
], JSON_PRETTY_PRINT);
