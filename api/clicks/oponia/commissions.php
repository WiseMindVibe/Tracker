<?php
require_once __DIR__ . '/../../../src/bootstrap.php';

date_default_timezone_set('UTC');

echo "Starting Oponia Commission Sync...\n";
echo "========================================\n";

$filter_advertiser_id = getInputParam('advertiser_id') ?? '9b0597281070a05c';
$filter_advertiser_name = getInputParam('advertiser_name');

if ($filter_advertiser_id !== null) {
    echo "Filter advertiser_id: {$filter_advertiser_id}\n";
}
if ($filter_advertiser_name !== null) {
    echo "Filter advertiser_name: {$filter_advertiser_name}\n";
}

// -------------------------
// CONFIG
// -------------------------
$start_date = new DateTime('2025-10-20');
$end_date   = new DateTime('2026-04-18');

$markets = ['es'];
//$markets = ['at', 'ca', 'dk', 'fi', 'fr', 'de', 'hu', 'il', 'it', 'mx', 'nl', 'no', 'pl', 'ro', 'es', 'se', 'ch', 'uk', 'us']; // add more if needed

$base_url = 'https://api.yieldads.net/report/commissions'; // adjust if needed

// -------------------------
// DB + API KEYS
// -------------------------
$db = db();

$stmt = $db->prepare("SELECT id FROM affiliate_accounts WHERE affiliate_program = 'oponia'");
$stmt->execute();
$affiliate_account_id = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT field_value
    FROM affiliate_account_credentials
    WHERE affiliate_account_id = :id AND field_key = 'API-Key'
");
$stmt->execute(['id' => $affiliate_account_id]);
$api_key = $stmt->fetchColumn();

// -------------------------
$total_processed = 0;
$error_count = 0;
$click_not_found_count = 0;

$status_summary = [
    'open' => ['count' => 0, 'amount' => 0.0],
    'confirmed' => ['count' => 0, 'amount' => 0.0],
    'rejected' => ['count' => 0, 'amount' => 0.0],
];

// -------------------------
// LOOP DAYS
// -------------------------
$current = clone $start_date;

while ($current <= $end_date) {

    $date_str = $current->format('Y-m-d');

    foreach ($markets as $market) {

        echo "\nFetching $date_str [$market]\n";

        $url = $base_url
            . '?date=' . urlencode($date_str)
            . '&market=' . urlencode($market);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'API-Key: ' . $api_key
            ],
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "cURL Error: " . curl_error($ch) . "\n";
            $error_count++;
            continue;
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (!isset($data['commissions'])) {
            echo "Invalid response\n";
            $error_count++;
            continue;
        }

        foreach ($data['commissions'] as $row) {

            $commission_id = $row['commissionId'];
            $click_tag     = $row['placementId'];
            $revenue       = (float)$row['revenue'];
            $status        = mapStatus($row['status']);
            $advertiser_id = $row['merchantId'];
            $advertiser_name = $row['merchantName'] ?? $row['advertiserName'] ?? null;

            if ($filter_advertiser_id !== null && (string)$advertiser_id !== (string)$filter_advertiser_id) {
                continue;
            }

            if (
                $filter_advertiser_name !== null
                && ($advertiser_name === null || stripos($advertiser_name, $filter_advertiser_name) === false)
            ) {
                continue;
            }

            $status_key = normalizeStatus($row['status'] ?? '');
            if (isset($status_summary[$status_key])) {
                $status_summary[$status_key]['count']++;
                $status_summary[$status_key]['amount'] += $revenue;
            }

            if (!$click_tag) {
                $error_count++;
                continue;
            }

            // -------------------------
            // Resolve click
            // -------------------------
            $stmt = $db->prepare("
                SELECT id, offer_id, campaign_id
                FROM clicks
                WHERE click_id = :click_id
            ");
            $stmt->execute(['click_id' => $click_tag]);
            $click = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$click) {
                echo "Click not found: $click_tag\n";
                $click_not_found_count++;
                $error_count++;
                continue;
            }

            $click_id_internal = $click['id'];

            // affiliate id
            $stmt = $db->prepare("
                SELECT affiliate_program_id
                FROM offers
                WHERE id = :offer_id
            ");
            $stmt->execute(['offer_id' => $click['offer_id']]);
            $affiliate_program_id = $stmt->fetchColumn();

            // -------------------------
            // INSERT notification (event log)
            // -------------------------
            $stmt = $db->prepare("
                INSERT INTO notifications
                (click_id, commission_id, offer_id, campaign_id, affiliate_id, revenue, status, is_read, event_type)
                VALUES
                (:click_id, :commission_id, :offer_id, :campaign_id, :affiliate_id, :revenue, :status, 0, 3)
            ");

            $stmt->execute([
                'click_id' => $click_id_internal,
                'commission_id' => $commission_id,
                'offer_id' => $click['offer_id'],
                'campaign_id' => $click['campaign_id'],
                'affiliate_id' => $affiliate_program_id,
                'revenue' => $revenue,
                'status' => $status,
            ]);

            // -------------------------
            // UPSERT conversion (state)
            // -------------------------
            $stmt = $db->prepare("
                INSERT INTO conversions
                (click_id, commission_id, revenue, status, advertiser_id, event_type)
                VALUES
                (:click_id, :commission_id, :revenue, :status, :advertiser_id, 'pull_request')
                ON DUPLICATE KEY UPDATE
                    revenue = VALUES(revenue),
                    status = VALUES(status),
                    advertiser_id = VALUES(advertiser_id)
            ");

            $stmt->execute([
                'click_id' => $click_id_internal,
                'commission_id' => $commission_id,
                'revenue' => $revenue,
                'status' => $status,
                'advertiser_id' => $advertiser_id
            ]);

            $advertiser_name_label = $advertiser_name ?: 'N/A';
            echo "Processed: $commission_id | $click_tag | $revenue | {$advertiser_id} | {$advertiser_name_label}\n";

            $total_processed++;
        }

        usleep(150000); // avoid rate limits
    }

    $current->modify('+1 day');
}

// -------------------------
echo "\n========================================\n";
echo "SYNC COMPLETE\n";
echo "Processed: $total_processed\n";
echo "Errors: $error_count\n";
echo "Click not found: $click_not_found_count\n";
echo "\nStatus Summary (from API response)\n";
echo "OPEN: count={$status_summary['open']['count']} amount=" . number_format($status_summary['open']['amount'], 2, '.', '') . "\n";
echo "CONFIRMED: count={$status_summary['confirmed']['count']} amount=" . number_format($status_summary['confirmed']['amount'], 2, '.', '') . "\n";
echo "REJECTED: count={$status_summary['rejected']['count']} amount=" . number_format($status_summary['rejected']['amount'], 2, '.', '') . "\n";

// -------------------------
function mapStatus($status)
{
    $status_map = [
        'open' => 1,
        'confirmed' => 2,
        'rejected' => 3,
        'paid' => 4,
    ];

    return $status_map[strtolower((string)$status)] ?? 0;
}

function normalizeStatus($status)
{
    return strtolower(trim((string)$status));
}

function getInputParam($key)
{
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        return trim((string)$_GET[$key]);
    }

    if (PHP_SAPI === 'cli' && isset($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
        foreach ($GLOBALS['argv'] as $argument) {
            if (strpos($argument, '--' . $key . '=') === 0) {
                $value = substr($argument, strlen('--' . $key . '='));
                return $value === '' ? null : trim($value);
            }
        }
    }

    return null;
}
