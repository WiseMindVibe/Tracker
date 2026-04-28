<?php
require_once __DIR__ . '/../../../src/bootstrap.php';

date_default_timezone_set('UTC');

echo "Starting Yieldkit sync...\n";
echo "========================================\n";

// -------------------------
// CONFIG
// -------------------------
$start_date = '2026-04-14';
$end_date   = '2026-04-18';

$base_url = 'https://account2.yieldkit.com/api/v3/reports/publisher/advertiser/click'
    . '?start_date=' . urlencode($start_date)
    . '&end_date=' . urlencode($end_date);

// -------------------------
// DB + API KEYS
// -------------------------
$db = db();

// get affiliate account
$stmt = $db->prepare("SELECT id FROM affiliate_accounts WHERE affiliate_program = 'yieldkit'");
$stmt->execute();
$affiliate_account_id = $stmt->fetchColumn();

// API KEY
$stmt = $db->prepare("
    SELECT field_value 
    FROM affiliate_account_credentials 
    WHERE affiliate_account_id = :id AND field_key = 'API-Key'
");
$stmt->execute(['id' => $affiliate_account_id]);
$api_key = $stmt->fetchColumn();

// API SECRET
$stmt = $db->prepare("
    SELECT field_value 
    FROM affiliate_account_credentials 
    WHERE affiliate_account_id = :id AND field_key = 'API-Secret'
");
$stmt->execute(['id' => $affiliate_account_id]);
$api_secret = $stmt->fetchColumn();


    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $api_key,
            'x-api-secret: ' . $api_secret,
        ],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        die("cURL Error: " . curl_error($ch));
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        die("HTTP Error $http_code: $response");
    }

    $data = json_decode($response, true);

    if (!isset($data['content'])) {
        die("Invalid response structure\n");
    }

    $rows_count = count($data['content']);
    echo "Processing $rows_count rows...\n";

    foreach ($data['content'] as $row) {

        $total_clicks += $row['statistic']['clicks_forwarded'] + $row['statistic']['clicks_untracked'] + $row['statistic']['clicks_blocked'];
        $total_commission += $row['statistic']['paid_commissions'] + $row['statistic']['confirmed_commissions'] + $row['statistic']['open_commissions']
         + $row['statistic']['rejected_commissions'] + $row['statistic']['delayed_commissions'];
        $total_revenue += $row['statistic']['total_commission'];

    }

    echo "Total clicks: $total_clicks\n";
    echo "Total commission: $total_commission\n";
    echo "Total revenue: $total_revenue\n";
