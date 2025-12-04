<?php

require_once __DIR__ . '/../src/bootstrap.php';

$mode = $_GET['mode'] ?? null;
$start = $_GET['start'] ?? date('Y-m-d 00:00:00');
$end   = $_GET['end']   ?? date('Y-m-d 23:59:59');

header('Content-Type: application/json');

// -------------------------------------------------------------
// LEVEL 1 — OFFERS
// -------------------------------------------------------------
if ($mode === 'offers') {
    $sql = "
        SELECT 
            o.id AS offer_id,
            o.name AS offer_name,

            COUNT(c.id) AS clicks,
            SUM(c.payout) AS revenue,
            SUM(c.cost) AS cost,
            SUM(c.payout) - SUM(c.cost) AS profit,
            SUM(c.status='converted') AS conversions

        FROM offers o
        LEFT JOIN clicks c ON c.offer_id = o.id AND c.created_at BETWEEN ? AND ?
        GROUP BY o.id
        HAVING clicks > 0
        ORDER BY clicks DESC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$start, $end]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// -------------------------------------------------------------
// LEVEL 2 — CAMPAIGNS under OFFER
// -------------------------------------------------------------
if ($mode === 'campaigns') {
    $offer_id = $_GET['offer_id'];

    $sql = "
        SELECT 
            c.campaign_id,
            (SELECT name FROM campaigns WHERE id = c.campaign_id) AS campaign_name,

            COUNT(c.id) AS clicks,
            SUM(c.payout) AS revenue,
            SUM(c.cost) AS cost,
            SUM(c.payout) - SUM(c.cost) AS profit,
            SUM(c.status='converted') AS conversions

        FROM clicks c
        WHERE c.offer_id = ?
          AND c.created_at BETWEEN ? AND ?
        GROUP BY c.campaign_id
        HAVING clicks > 0
        ORDER BY clicks DESC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$offer_id, $start, $end]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// -------------------------------------------------------------
// LEVEL 3 — OS under CAMPAIGN+OFFER
// -------------------------------------------------------------
if ($mode === 'os') {
    $offer_id = $_GET['offer_id'];
    $campaign_id = $_GET['campaign_id'];

    $sql = "
        SELECT 
            OS,
            COUNT(id) AS clicks,
            SUM(payout) AS revenue,
            SUM(cost) AS cost,
            SUM(payout) - SUM(cost) AS profit,
            SUM(status='converted') AS conversions

        FROM clicks
        WHERE offer_id = ?
          AND campaign_id = ?
          AND created_at BETWEEN ? AND ?
        GROUP BY OS
        HAVING clicks > 0
        ORDER BY clicks DESC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$offer_id, $campaign_id, $start, $end]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// -------------------------------------------------------------
// LEVEL 4 — BROWSER under OS
// -------------------------------------------------------------
if ($mode === 'browser') {
    $offer_id = $_GET['offer_id'];
    $campaign_id = $_GET['campaign_id'];
    $os = $_GET['os'];

    $sql = "
        SELECT 
            browser,
            COUNT(id) AS clicks,
            SUM(payout) AS revenue,
            SUM(cost) AS cost,
            SUM(payout) - SUM(cost) AS profit,
            SUM(status='converted') AS conversions

        FROM clicks
        WHERE offer_id = ?
          AND campaign_id = ?
          AND OS = ?
          AND created_at BETWEEN ? AND ?
        GROUP BY browser
        HAVING clicks > 0
        ORDER BY clicks DESC
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$offer_id, $campaign_id, $os, $start, $end]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

echo json_encode(['error' => 'Invalid mode']);
