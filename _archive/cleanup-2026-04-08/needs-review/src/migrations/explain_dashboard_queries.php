<?php
/**
 * CLI: verify dashboard-related plans after adding indexes (006.2).
 *
 * Usage: php src/migrations/explain_dashboard_queries.php
 * Requires .env DB_* and migration 006.2 applied for best results.
 */
require_once __DIR__ . '/../bootstrap.php';

$db = db();
$start = (new DateTime('today'))->format('Y-m-d');
$bounds = [
    ':start' => $start . ' 00:00:00',
    ':end' => $start . ' 23:59:59',
];

$queries = [
    'clicks_aggregate' => '
        SELECT COUNT(*) AS total_clicks, COALESCE(SUM(c.cost), 0) AS cost
        FROM clicks c
        WHERE c.created_at >= :start AND c.created_at <= :end
    ',
    'conversions_join_clicks' => '
        SELECT COUNT(*) AS cnt
        FROM conversions cv
        INNER JOIN clicks c ON c.id = cv.click_id
        WHERE c.created_at >= :start AND c.created_at <= :end
    ',
];

foreach ($queries as $label => $sql) {
    echo "\n=== EXPLAIN {$label} ===\n";
    $stmt = $db->prepare('EXPLAIN ' . $sql);
    $stmt->execute($bounds);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n=== EXPLAIN ANALYZE clicks_aggregate (MySQL 8.0.18+; optional) ===\n";
try {
    $stmt = $db->prepare('EXPLAIN ANALYZE ' . $queries['clicks_aggregate']);
    $stmt->execute($bounds);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
    }
} catch (Throwable $e) {
    echo '(skipped: ' . $e->getMessage() . ")\n";
}

echo "\nDone.\n";

