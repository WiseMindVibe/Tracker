<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$campaignId = $_GET['campaign_id'] ?? null;

if (!$campaignId) {
    echo json_encode(['error' => 'Campaign ID is required']);
    exit;
}

    $db = db();

    $stmt = $db->prepare("SELECT
    c.traffic_source_id,
    ts.name AS traffic_source_name,
    ts.api_key
    FROM campaigns c
    LEFT JOIN traffic_sources ts ON ts.id = c.traffic_source_id
    WHERE c.id = :campaign_id
    ");
    $stmt->execute(['campaign_id' => $campaignId]);
    $trafficSourceDetails = $stmt->fetch();

    $stmt2 = $db->prepare("SELECT
    external_campaign_id
    FROM campaign_external_ids
    WHERE campaign_id = :campaign_id
    ");
    $stmt2->execute(['campaign_id' => $campaignId]);
    $externalCampaignIdDetails = $stmt2->fetchAll();

    $externalCampaignIds = array_column($externalCampaignIdDetails, 'external_campaign_id');

        
    switch (strtolower($trafficSourceDetails['traffic_source_name'])) {
        case 'propellerads':
            $api_url = "https://ssp-api.propellerads.com/v5/adv/campaigns/stop";
            $payload = [
                'campaign_ids' => $externalCampaignIds
            ];

            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $api_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $trafficSourceDetails['api_key'],
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30
                ]);

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    return [
                        'success' => false,
                        'error' => curl_error($ch)
                    ];
                }
                curl_close($ch);

                return [
                    'success' => true,
                    'data' => json_decode($response, true)
                ];
            break;
        case 'hilltops':
            return true;
            break;
        default:
            return false;
            break;
    }
