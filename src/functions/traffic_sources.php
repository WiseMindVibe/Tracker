<?php

function getTrafficSources() {
    $db = db();
    $stmt = $db->query("SELECT * FROM traffic_sources ORDER BY id ASC");
    return $stmt->fetchAll();
}

function getTrafficSource($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM traffic_sources WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function addTrafficSource($name, $api_key) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO traffic_sources (name, api_key) VALUES (?, ?)");
    return $stmt->execute([$name, $api_key]);
}

function updateTrafficSource($id, $name, $api_key) {
    $db = db();
    $stmt = $db->prepare("UPDATE traffic_sources SET name = ?, api_key = ? WHERE id = ?");
    return $stmt->execute([$name, $api_key, $id]);
}

function deleteTrafficSource($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM traffic_sources WHERE id = ?");
    return $stmt->execute([$id]);
}

function stopTrafficSourceCampaign($trafficSourceId, array $externalCampaignIds) {
    if (empty($externalCampaignIds)) return false;

    // 1. Get traffic source info
    $stmt = db()->prepare("SELECT * FROM traffic_sources WHERE id = :id");
    $stmt->execute([':id' => $trafficSourceId]);
    $ts = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ts) return false;

    $apiKey = $ts['api_key'];
    $sourceName = strtolower(trim($ts['name'] ?? ''));

    // 2. Determine API endpoint and payload based on traffic source
    switch ($sourceName) {
        case 'propellerads':
            $apiUrl = "https://ssp-api.propellerads.com/v5/adv/campaigns/stop";
            $payload = [
                "campaign_ids" => array_map('intval', $externalCampaignIds)
            ];
            break;
        default:
            return false; // unsupported traffic source
    }

    // 3. Make PUT request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 4. Log result
    $stmt = db()->prepare("
        INSERT INTO traffic_source_logs (traffic_source_id, campaign_id, response, http_code)
        VALUES (:tsid, :cid, :response, :http_code)
    ");
    foreach ($externalCampaignIds as $cid) {
        $stmt->execute([
            ':tsid' => $trafficSourceId,
            ':cid' => $cid,
            ':response' => $response,
            ':http_code' => $httpCode
        ]);
    }

    return $httpCode >= 200 && $httpCode < 300;
}

