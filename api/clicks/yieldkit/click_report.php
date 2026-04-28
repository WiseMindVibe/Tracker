<?php
require_once __DIR__ . '/../../../src/bootstrap.php';

date_default_timezone_set('UTC');

echo "Starting Yieldkit sync...\n";
echo "========================================\n";

// -------------------------
// CONFIG
// -------------------------
$start_date = '2026-04-14T00:00:00Z';
$end_date   = '2026-04-18T21:59:59Z';

$base_url = 'https://account2.yieldkit.com/api/v3/reports/commissions/sales?format=json'
    . '&start_date=' . urlencode($start_date)
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

// -------------------------
// PAGINATION LOOP
// -------------------------
$current_url = $base_url;
$total_processed = 0;
$page = 1;

while ($current_url) {

    echo "\n[Page $page] Fetching...\n";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $current_url,
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

    $total_revenue = 0;
    $error_count = 0;

    foreach ($data['content'] as $row) {

        $commission_id = $row['id'];
        $revenue       = $row['commission'];
        $total_revenue += $revenue;

        $status_raw = $row['state'];
        $status = mapStatus($status_raw);
        
        $sale_date     = $row['date'];
        $click_tag     = $row['ykTag'] ?? null;
        $advertiser_id = $row['advertiserId'];
        $modified_date = $row['modified_date'] ?? $sale_date;

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
            /*
            $stmt = $db->prepare("INSERT INTO clicks (click_id, offer_id, campaign_id) VALUES (:click_id, :offer_id, :campaign_id)");
            $stmt->execute(['click_id' => $click_tag, 'offer_id' => $click['offer_id'], 'campaign_id' => $click['campaign_id']]);
            */
            echo "Click not found! Skipping..." . "\n";
            $error_count++;
            continue;
        }

        $click_id_internal = $click['id'];

        // get affiliate_program_id
        $stmt = $db->prepare("
            SELECT affiliate_program_id 
            FROM offers 
            WHERE id = :offer_id
        ");
        $stmt->execute(['offer_id' => $click['offer_id']]);
        $affiliate_program_id = $stmt->fetchColumn();

        // -------------------------
        // 1. INSERT EVENT (notifications)
        // -------------------------
        $stmt = $db->prepare("
            INSERT INTO notifications
            (click_id, commission_id, offer_id, campaign_id, affiliate_id, revenue, status, is_read, sale_date, modified_date, event_type)
            VALUES
            (:click_id, :commission_id, :offer_id, :campaign_id, :affiliate_id, :revenue, :status, 0, :sale_date, :modified_date, 3)
        ");

        $stmt->execute([
            'click_id' => $click_id_internal,
            'commission_id' => $commission_id,
            'offer_id' => $click['offer_id'],
            'campaign_id' => $click['campaign_id'],
            'affiliate_id' => $affiliate_program_id,
            'revenue' => $revenue,
            'status' => $status,
            'sale_date' => $sale_date,
            'modified_date' => $modified_date
        ]);

        // -------------------------
        // 2. UPSERT STATE (conversions)
        // -------------------------
        $stmt = $db->prepare("
            INSERT INTO conversions
            (click_id, commission_id, revenue, status, sale_date, modified_date, advertiser_id, event_type)
            VALUES
            (:click_id, :commission_id, :revenue, :status, :sale_date, :modified_date, :advertiser_id, 'pull_request')
            ON DUPLICATE KEY UPDATE
                click_id = VALUES(click_id),
                commission_id = VALUES(commission_id),
                revenue = VALUES(revenue),
                status = VALUES(status),
                sale_date = VALUES(sale_date),
                modified_date = VALUES(modified_date),
                advertiser_id = VALUES(advertiser_id)
        ");

        $stmt->execute([
            'click_id' => $click_id_internal,
            'commission_id' => $commission_id,
            'revenue' => $revenue,
            'status' => $status,
            'sale_date' => $sale_date,
            'modified_date' => $modified_date,
            'advertiser_id' => $advertiser_id
        ]);

        echo "Processed commission: " . $commission_id . " - " . $click_id_internal . " - " . $revenue . " - " . $status . " - "
         . $modified_date . "\n";

        $total_processed++;

        // Optional: print every 100 rows
        if ($total_processed % 100 === 0) {
            echo "Processed $total_processed rows...\n";
        }
    }

    // -------------------------
    // NEXT PAGE
    // -------------------------
    $next_url = $data['next'] ?? null;

    if ($next_url && $next_url !== $data['self']) {
        $current_url = $next_url;
        $page++;
    } else {
        $current_url = null;
    }

    echo "Page $page complete. Total so far: $total_processed\n";
    echo "----------------------------------------\n";
}

echo "\n========================================\n";
echo "SYNC COMPLETE\n";
echo "Total processed: $total_processed\n";
echo "Total errors: $error_count\n";
echo "Total revenue: $total_revenue\n";

function mapStatus($status)
{
    switch (strtolower($status)) {
        case 'open':
            return 1;

        case 'confirmed':
            return 2;

        case 'rejected':
            return 3;

        case 'paid':
            return 4;

        default:
            return 0; // unknown / fallback
    }
}