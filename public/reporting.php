<?php

require_once __DIR__ . '/../src/bootstrap.php';

$start = $_GET['start'] ?? date('Y-m-d 00:00:00');
$end   = $_GET['end']   ?? date('Y-m-d 23:59:59');

$sql = "
SELECT 
    o.id AS offer_id,
    o.name AS offer_name,

    COUNT(c.id) AS clicks,
    SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) AS conversions,

    IFNULL(SUM(c.payout), 0) AS revenue,
    IFNULL(SUM(c.cost), 0) AS cost,
    (IFNULL(SUM(c.payout), 0) - IFNULL(SUM(c.cost), 0)) AS profit,

    CASE WHEN COUNT(c.id) = 0 THEN 0
         ELSE (SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) / COUNT(c.id)) * 100
    END AS cr,

    CASE WHEN IFNULL(SUM(c.cost), 0) = 0 THEN 0
         ELSE ((IFNULL(SUM(c.payout), 0) - IFNULL(SUM(c.cost), 0)) / SUM(c.cost)) * 100
    END AS roi

FROM offers o
LEFT JOIN clicks c ON c.offer_id = o.id
WHERE c.created_at BETWEEN ? AND ?
GROUP BY o.id
HAVING clicks > 0
ORDER BY clicks DESC
";

$stmt = db()->prepare($sql);
$stmt->execute([$start, $end]);
$report = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../views/reporting.php';
