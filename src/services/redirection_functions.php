<?php
function generateClickId(): string
{
    $prefix = 'cid';

    // 7 bytes → 14 hex chars (similar to your example length)
    $randomHex = bin2hex(random_bytes(7));

    // 8-digit numeric suffix
    $numeric = random_int(10000000, 99999999);

    return $prefix . $randomHex . '.' . $numeric;
}

function stopTrafficSourceCampaigns(string $campaignId){

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

    if (!$trafficSourceDetails || empty($externalCampaignIds)) {
        return [
            'success' => false,
            'error' => 'Missing traffic source details or external campaign ids'
        ];
    }

    switch (strtolower((string) $trafficSourceDetails['traffic_source_name'])) {
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
        case 'hilltopads':
            $results = [];
            foreach ($externalCampaignIds as $externalCampaignId) {
                $api_url = "https://api.hilltopads.com/advertiser/stopCampaign?key=" .
                    urlencode((string) $trafficSourceDetails['api_key']) .
                    "&campaignId=" . urlencode((string) $externalCampaignId);

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $api_url,
                    CURLOPT_CUSTOMREQUEST => 'PATCH',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30
                ]);

                $response = curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_errno($ch) ? curl_error($ch) : null;
                curl_close($ch);

                $results[] = [
                    'external_campaign_id' => $externalCampaignId,
                    'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'curl_error' => $curlError,
                    'response' => $response
                ];
            }

            return [
                'success' => true,
                'data' => $results
            ];
            break;
        case 'popcash':
            $results = [];
            foreach ($externalCampaignIds as $externalCampaignId) {
                $api_url = "https://api.popcash.net/campaign/" . rawurlencode((string) $externalCampaignId);
                $payload = [
                    'status' => 0
                ];

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $api_url,
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'X-Api-Key: ' . $trafficSourceDetails['api_key'],
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

                $results[] = [
                    'external_campaign_id' => $externalCampaignId,
                    'success' => $curlError === null && $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'curl_error' => $curlError,
                    'response' => $response
                ];
            }

            return [
                'success' => true,
                'data' => $results
            ];
            break;
        default:
            return false;
            break;
    }
}
 
function buildAffiliateTrackingToken(string $affiliateProgram)
{

    switch (strtolower($affiliateProgram)) {
        case 'yieldkit':
            return 'yk_tag';

        case 'oponia':
            return 'placementId';

        default:
            throw new Exception("Unsupported affiliate program: " . $affiliateProgram);
    }
}

function sendTelegramMessage(string $message): bool
{
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');

    if (!$botToken || !$chatId) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);

    curl_exec($ch);
    curl_close($ch);

    return true;
}
