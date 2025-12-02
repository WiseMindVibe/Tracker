<?php

function logRedirect($campaignID, $status, $reason = null) {
    $stmt = db()->prepare("
        INSERT INTO redirect_logs (campaign_id, status, reason, raw_query, ip)
        VALUES (:campaign_id, :status, :reason, :raw_query, INET6_ATON(:ip))
    ");

    $stmt->execute([
        ':campaign_id'  => $campaignID,
        ':status'    => $status,
        ':reason'    => $reason,
        ':raw_query' => json_encode($_GET),  // Store full request in JSON
        ':ip'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}
