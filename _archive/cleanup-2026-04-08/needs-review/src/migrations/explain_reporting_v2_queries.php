<?php
/**
 * CLI helper to compare legacy-vs-optimized reporting query plans.
 *
 * Usage:
 *   c:\XAMPP2\php\php.exe src/migrations/explain_reporting_v2_queries.php
 */
require_once __DIR__ . '/../bootstrap.php';

$db = db();
$tz = new DateTimeZone(date_default_timezone_get());
$today = new DateTimeImmutable('today', $tz);

$dateFrom = $today->modify('-6 days')->format('Y-m-d') . ' 00:00:00';
$dateTo = $today->format('Y-m-d') . ' 23:59:59';

$sampleStmt = $db->prepare(
    'SELECT
        COALESCE(MAX(offer_id), 1) AS offer_id,
        COALESCE(MAX(campaign_id), 1) AS campaign_id
     FROM clicks
     WHERE created_at >= :date_from AND created_at <= :date_to'
);
$sampleStmt->execute([
    ':date_from' => $dateFrom,
    ':date_to' => $dateTo,
]);
$sample = $sampleStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$offerId = max(1, (int) ($sample['offer_id'] ?? 1));
$campaignId = max(1, (int) ($sample['campaign_id'] ?? 1));

$baseParams = [
    ':date_from' => $dateFrom,
    ':date_to' => $dateTo,
    ':offer_id' => $offerId,
    ':campaign_id' => $campaignId,
];

$queries = [
    'offer-order/root-offer/legacy' => '
        SELECT
            COALESCE(o.id, 0) AS group_key,
            COALESCE(MAX(o.name), "(no offer)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        WHERE c.created_at >= :date_from AND c.created_at <= :date_to
        GROUP BY COALESCE(o.id, 0)
    ',
    'offer-order/root-offer/optimized' => '
        SELECT
            COALESCE(c.offer_id, 0) AS group_key,
            COALESCE(MAX(o.name), "(no offer)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        WHERE c.created_at >= :date_from AND c.created_at <= :date_to
        GROUP BY COALESCE(c.offer_id, 0)
    ',
    'offer-order/child-campaign/legacy' => '
        SELECT
            COALESCE(cam_uuid.id, cam_id.id, 0) AS group_key,
            COALESCE(MAX(cam_uuid.name), MAX(cam_id.name), "(no campaign)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        LEFT JOIN campaigns cam_uuid ON cam_uuid.uuid = c.campaign_id
        LEFT JOIN campaigns cam_id ON cam_id.id = c.campaign_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
        GROUP BY COALESCE(cam_uuid.id, cam_id.id, 0)
    ',
    'offer-order/child-campaign/optimized' => '
        SELECT
            COALESCE(c.campaign_id, 0) AS group_key,
            COALESCE(MAX(cam.name), "(no campaign)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN campaigns cam ON cam.id = c.campaign_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
        GROUP BY COALESCE(c.campaign_id, 0)
    ',
    'offer-order/child-os/legacy' => '
        SELECT
            COALESCE(NULLIF(TRIM(c.`OS`), ""), "(none)") AS group_key,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        LEFT JOIN campaigns cam_uuid ON cam_uuid.uuid = c.campaign_id
        LEFT JOIN campaigns cam_id ON cam_id.id = c.campaign_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
          AND COALESCE(cam_uuid.id, cam_id.id, 0) = :campaign_id
        GROUP BY COALESCE(NULLIF(TRIM(c.`OS`), ""), "(none)")
    ',
    'offer-order/child-os/optimized' => '
        SELECT
            COALESCE(NULLIF(c.`OS`, ""), "(none)") AS group_key,
            COUNT(c.id) AS clicks
        FROM clicks c
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
          AND c.campaign_id = :campaign_id
        GROUP BY COALESCE(NULLIF(c.`OS`, ""), "(none)")
    ',
    'campaign-order/root-campaign/legacy' => '
        SELECT
            COALESCE(cam_uuid.id, cam_id.id, 0) AS group_key,
            COALESCE(MAX(cam_uuid.name), MAX(cam_id.name), "(no campaign)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN campaigns cam_uuid ON cam_uuid.uuid = c.campaign_id
        LEFT JOIN campaigns cam_id ON cam_id.id = c.campaign_id
        WHERE c.created_at >= :date_from AND c.created_at <= :date_to
        GROUP BY COALESCE(cam_uuid.id, cam_id.id, 0)
    ',
    'campaign-order/root-campaign/optimized' => '
        SELECT
            COALESCE(c.campaign_id, 0) AS group_key,
            COALESCE(MAX(cam.name), "(no campaign)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN campaigns cam ON cam.id = c.campaign_id
        WHERE c.created_at >= :date_from AND c.created_at <= :date_to
        GROUP BY COALESCE(c.campaign_id, 0)
    ',
    'campaign-order/child-offer/legacy' => '
        SELECT
            COALESCE(o.id, 0) AS group_key,
            COALESCE(MAX(o.name), "(no offer)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        LEFT JOIN campaigns cam_uuid ON cam_uuid.uuid = c.campaign_id
        LEFT JOIN campaigns cam_id ON cam_id.id = c.campaign_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND COALESCE(cam_uuid.id, cam_id.id, 0) = :campaign_id
        GROUP BY COALESCE(o.id, 0)
    ',
    'campaign-order/child-offer/optimized' => '
        SELECT
            COALESCE(c.offer_id, 0) AS group_key,
            COALESCE(MAX(o.name), "(no offer)") AS group_name,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.campaign_id = :campaign_id
        GROUP BY COALESCE(c.offer_id, 0)
    ',
    'campaign-order/child-os/legacy' => '
        SELECT
            COALESCE(NULLIF(TRIM(c.`OS`), ""), "(none)") AS group_key,
            COUNT(c.id) AS clicks
        FROM clicks c
        LEFT JOIN offers o ON o.id = c.offer_id
        LEFT JOIN campaigns cam_uuid ON cam_uuid.uuid = c.campaign_id
        LEFT JOIN campaigns cam_id ON cam_id.id = c.campaign_id
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
          AND COALESCE(cam_uuid.id, cam_id.id, 0) = :campaign_id
        GROUP BY COALESCE(NULLIF(TRIM(c.`OS`), ""), "(none)")
    ',
    'campaign-order/child-os/optimized' => '
        SELECT
            COALESCE(NULLIF(c.`OS`, ""), "(none)") AS group_key,
            COUNT(c.id) AS clicks
        FROM clicks c
        WHERE c.created_at >= :date_from
          AND c.created_at <= :date_to
          AND c.offer_id = :offer_id
          AND c.campaign_id = :campaign_id
        GROUP BY COALESCE(NULLIF(c.`OS`, ""), "(none)")
    ',
];

$filterParams = static function (string $sql, array $params): array {
    $out = [];
    foreach ($params as $key => $value) {
        if (str_contains($sql, (string) $key)) {
            $out[$key] = $value;
        }
    }

    return $out;
};

$explainRows = static function (PDO $db, string $sql, array $params): array {
    $stmt = $db->prepare('EXPLAIN ' . $sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
};

$measureMs = static function (PDO $db, string $sql, array $params): float {
    $start = microtime(true);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stmt->fetchAll(PDO::FETCH_ASSOC);
    return (microtime(true) - $start) * 1000.0;
};

echo "Reporting v2 plan comparison\n";
echo "Date range: {$dateFrom} .. {$dateTo}\n";
echo "Sample filters: offer_id={$offerId}, campaign_id={$campaignId}\n\n";

foreach ($queries as $label => $sql) {
    echo "=== {$label} ===\n";
    $queryParams = $filterParams($sql, $baseParams);
    $rows = $explainRows($db, $sql, $queryParams);
    foreach ($rows as $row) {
        echo json_encode([
            'table' => $row['table'] ?? null,
            'type' => $row['type'] ?? null,
            'key' => $row['key'] ?? null,
            'rows' => $row['rows'] ?? null,
            'extra' => $row['Extra'] ?? null,
        ], JSON_UNESCAPED_SLASHES) . "\n";
    }

    try {
        $ms = $measureMs($db, $sql, $queryParams);
        echo 'runtime_ms=' . number_format($ms, 3, '.', '') . "\n";
    } catch (Throwable $e) {
        echo 'runtime_ms=error:' . $e->getMessage() . "\n";
    }
    echo "\n";
}

