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

    if (!$ts || empty($ts['api_key']) || empty($ts['name'])) {
        error_log("Traffic source missing or invalid API key/name");
        return false;
    }

    $apiKey = trim($ts['api_key']);
    $sourceName = strtolower(trim($ts['name']));

    // 2. Determine API endpoint and payload
    switch ($sourceName) {
        case 'propellerads':
            $apiUrl = "https://ssp-api.propellerads.com/v5/adv/campaigns/stop";
            $payload = [
                "campaign_ids" => array_map('intval', $externalCampaignIds)
            ];
            break;
        default:
            error_log("Unsupported traffic source: $sourceName");
            return false;
    }

    // 3. Initialize cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);           // timeout

    // 4. Execute cURL and capture debug info
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("cURL error: $error");
        return false;
    }

    curl_close($ch);

    // 5. Log each campaign stop attempt
    $stmtLog = db()->prepare("
        INSERT INTO traffic_source_logs 
        (traffic_source_id, campaign_id, response, http_code, created_at) 
        VALUES (:tsid, :cid, :response, :http_code, NOW())
    ");
    foreach ($externalCampaignIds as $cid) {
        $stmtLog->execute([
            ':tsid' => $trafficSourceId,
            ':cid' => $cid,
            ':response' => $response,
            ':http_code' => $httpCode
        ]);
    }

    // 6. Debug output (optional — remove in production)
    echo "HTTP CODE: $httpCode\n";
    echo "RESPONSE: $response\n";
    echo "PAYLOAD SENT: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

    // 7. Return true only if API confirmed success (HTTP 2xx)
    return $httpCode >= 200 && $httpCode < 300;
}



