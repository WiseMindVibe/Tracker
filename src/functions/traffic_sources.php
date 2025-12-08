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

function stopTrafficSourceCampaign($trafficSourceId, $externalCampaignId) {

    // 1. Get API credentials & settings
    $stmt = db()->prepare("SELECT * FROM traffic_sources WHERE id = :id");
    $stmt->execute([':id' => $trafficSourceId]);
    $ts = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ts) return false;

    $apiKey = $ts['api_key'];
    $apiUrl = $ts['api_pause_endpoint']; // example: https://api.propellerads.com/v5/campaigns/{id}/status

    // 2. Replace campaign ID inside URL
    $apiUrl = str_replace("{campaign_id}", $externalCampaignId, $apiUrl);

    // 3. Make request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["status" => "paused"]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 4. Log result
    $stmt = db()->prepare("
        INSERT INTO traffic_source_logs (traffic_source_id, campaign_id, response, http_code)
        VALUES (:tsid, :cid, :response, :http_code)
    ");
    $stmt->execute([
        ':tsid' => $trafficSourceId,
        ':cid' => $externalCampaignId,
        ':response' => $response,
        ':http_code' => $httpCode
    ]);

    return $httpCode >= 200 && $httpCode < 300;
}
