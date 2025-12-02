<?php

function logPostback($clickId, $status, $reason = null) {
    $stmt = db()->prepare("
        INSERT INTO postback_logs (click_id, status, reason, raw_query, ip)
        VALUES (:click_id, :status, :reason, :raw_query, INET6_ATON(:ip))
    ");

    $stmt->execute([
        ':click_id'  => $clickId,
        ':status'    => $status,
        ':reason'    => $reason,
        ':raw_query' => json_encode($_GET),  // Store full request in JSON
        ':ip'        => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    ]);
}
