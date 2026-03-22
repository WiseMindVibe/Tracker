<?php

/**
 * Normalize external ids (stored in campaign_external_ids.external_campaign_id).
 *
 * @param mixed $raw
 * @return list<string>
 */
function normalizeExternalIds($raw): array
{
    $out = [];
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_array($raw)) {
        foreach ($raw as $v) {
            $v2 = trim((string) $v);
            if ($v2 !== '') {
                $out[] = $v2;
            }
        }
        return array_values(array_unique($out));
    }
    $raw = trim((string) $raw);
    if (strpos($raw, '[') === 0 && strpos($raw, ']') !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return normalizeExternalIds($decoded);
        }
    }
    if (strpos($raw, ',') !== false) {
        $parts = array_map('trim', explode(',', $raw));
        return normalizeExternalIds($parts);
    }
    if ($raw !== '') {
        return [$raw];
    }
    return [];
}

/**
 * @param list<string|int> $externalCampaignIds
 * @param int|null $internalCampaignId campaigns.id (for debug logs)
 * @return array<string, mixed>
 */
function stopTrafficSourceCampaign(int $trafficSourceId, array $externalCampaignIds, ?int $internalCampaignId = null): array
{
    return trafficSourceCampaignAction($trafficSourceId, $externalCampaignIds, 'stop', $internalCampaignId);
}

/**
 * @param list<string|int> $externalCampaignIds
 * @param int|null $internalCampaignId campaigns.id (for debug logs)
 * @return array<string, mixed>
 */
function startTrafficSourceCampaign(int $trafficSourceId, array $externalCampaignIds, ?int $internalCampaignId = null): array
{
    return trafficSourceCampaignAction($trafficSourceId, $externalCampaignIds, 'start', $internalCampaignId);
}

/**
 * @param 'start'|'stop' $action
 * @param list<string|int> $externalCampaignIds
 * @param int|null $internalCampaignId
 * @return array<string, mixed>
 */
function trafficSourceCampaignAction(int $trafficSourceId, array $externalCampaignIds, string $action, ?int $internalCampaignId = null): array
{
    if ($externalCampaignIds === []) {
        return ['success' => false, 'reason' => 'no_external_ids'];
    }

    try {
        $stmt = db()->prepare('SELECT * FROM traffic_sources WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $trafficSourceId]);
        $ts = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['success' => false, 'reason' => 'db_error_fetching_traffic_source', 'error' => $e->getMessage()];
    }

    if (!$ts || empty($ts['api_key']) || empty($ts['name'])) {
        return ['success' => false, 'reason' => 'invalid_traffic_source'];
    }

    $apiKey = trim($ts['api_key']);
    $sourceName = strtolower(trim($ts['name']));
    $payload = null;
    $apiUrl = null;
    $httpMethod = 'PUT';
    $headers = [];

    if ($action === 'stop') {
        switch ($sourceName) {
            case 'propellerads':
                $apiUrl = 'https://ssp-api.propellerads.com/v5/adv/campaigns/stop';
                $payload = ['campaign_ids' => array_map('intval', $externalCampaignIds)];
                $headers = [
                    "Authorization: Bearer {$apiKey}",
                    'Content-Type: application/json',
                ];
                break;
            case 'hilltop':
                // No public stop API wired yet — log-friendly no-op for callers
                return ['success' => false, 'reason' => 'unsupported_traffic_source', 'source' => $sourceName];
            default:
                return ['success' => false, 'reason' => 'unsupported_traffic_source', 'source' => $sourceName];
        }
    } elseif ($action === 'start') {
        switch ($sourceName) {
            case 'propellerads':
                $apiUrl = 'https://ssp-api.propellerads.com/v5/adv/campaigns/start';
                $payload = ['campaign_ids' => array_map('intval', $externalCampaignIds)];
                $headers = [
                    "Authorization: Bearer {$apiKey}",
                    'Content-Type: application/json',
                ];
                break;
            case 'hilltop':
                return ['success' => false, 'reason' => 'unsupported_traffic_source', 'source' => $sourceName];
            default:
                return ['success' => false, 'reason' => 'unsupported_traffic_source', 'source' => $sourceName];
        }
    } else {
        return ['success' => false, 'reason' => 'invalid_action'];
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = null;
    if (curl_errno($ch)) {
        $curlErr = curl_error($ch);
    }
    curl_close($ch);

    try {
        $stmtLog = db()->prepare('INSERT INTO traffic_source_logs (traffic_source_id, internal_campaign_id, campaign_id, response, http_code, created_at) VALUES (:tsid, :internal_cid, :ext_cid, :response, :http_code, NOW())');
        foreach ($externalCampaignIds as $extId) {
            $stmtLog->execute([
                ':tsid' => $trafficSourceId,
                ':internal_cid' => $internalCampaignId,
                ':ext_cid' => (string) $extId,
                ':response' => substr((string) $response, 0, 65500),
                ':http_code' => (int) $httpCode,
            ]);
        }
    } catch (Throwable $e) {
    }

    $success = $httpCode >= 200 && $httpCode < 300 && $curlErr === null;

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'curl_error' => $curlErr,
        'response' => $response,
        'traffic_source' => $sourceName,
        'external_campaign_ids' => $externalCampaignIds,
        'action' => $action,
    ];
}
