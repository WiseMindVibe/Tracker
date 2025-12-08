<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/functions/log_redirect.php';

// ==========================================
// 1. Extract GET parameters
// ==========================================

$clickid    = isset($_GET['SUBID']) ? $_GET['SUBID'] : null;
$campaignId = isset($_GET['cid']) ? intval($_GET['cid']) : null;
$external_campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : null;
$country    = isset($_GET['country']) ? $_GET['country'] : null;
$os         = $_GET['os'] ?? 'Unknown';
$browser    = $_GET['browser'] ?? 'Unknown';
$connection_type = isset($_GET['connection_type']) ? $_GET['connection_type'] : 'Unknown';
$isp        = isset($_GET['isp']) ? $_GET['isp'] : 'Unknown';
$carrier    = isset($_GET['carrier']) ? $_GET['carrier'] : 'Unknown';
$zone_id = isset($_GET['zone_id']) ? intval($_GET['zone_id']): 'Unknown';
$cost       = isset($_GET['cost']) ? floatval($_GET['cost']) : 'Unknown';
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ==========================================
// 2. Validate campaign exists
// ==========================================
$stmt = db()->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $campaignId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    logRedirect($campaignId, "Campaign not found", null);
    exit("Error: Campaign not found");
}

// ==========================================
// 3+4. Fetch campaign offers + cap check + balanced selection
// ==========================================

// Fetch campaign offers with cap and current_views
$stmt = db()->prepare("
    SELECT 
        co.offer_id,
        co.cap,
        co.current_views,
        o.name AS offer_name,
        o.affiliate_link,
        o.country,
        o.affiliate_program_id,
        o.website_id
    FROM campaign_offers co
    JOIN offers o ON o.id = co.offer_id
    WHERE co.campaign_id = :cid
");
$stmt->execute([':cid' => $campaignId]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$offers) {
    logRedirect($campaignId, "No offers in campaign", null);
    exit("Error: No offers in this campaign");
}

$availableOffers = [];
$allCapped = true;

// Check which offers are still valid (not fully capped)
foreach ($offers as $offer) {

    $views = (int)($offer['current_views'] ?? 0);
    $cap   = (int)$offer['cap'];

    if ($views < $cap) {
        $allCapped = false;
        $offer['current_views'] = $views;
        $offer['fill_percent'] = $views / $cap;
        $availableOffers[] = $offer;
    }
}


    // If all offers are capped
    if ($allCapped) {
        // 🔥 Placeholder: Stop campaign via traffic source API
        // sendStopCampaignToTrafficSource($campaignId);

        // Fetch traffic source associated with the campaign
    $stmt = db()->prepare("
        SELECT traffic_source_id, external_campaign_id
        FROM campaigns 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $campaignId]);
    $cdata = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cdata && $cdata['traffic_source_id'] && $cdata['external_campaign_id']) {

        stopTrafficSourceCampaign(
            $cdata['traffic_source_id'],
            $cdata['external_campaign_id']
        );

        logRedirect($campaignId, "Campaign capped → traffic source paused", null);

    } else {
        logRedirect($campaignId, "Campaign capped but no traffic source settings found", null);
    }

    // Redirect backup
    header("Location: https://google.com");
    exit;
}

// Sort available offers by lowest fill percentage
usort($availableOffers, function($a, $b) {
    return $a['fill_percent'] <=> $b['fill_percent'];
});

// Pick one randomly among the top 50% least filled offers
$halfIndex = ceil(count($availableOffers) / 2);
$topOffers = array_slice($availableOffers, 0, $halfIndex);
$selectedOffer = $topOffers[array_rand($topOffers)];

// Increment current_views for selected offer
$stmt = db()->prepare("
    UPDATE campaign_offers 
    SET current_views = COALESCE(current_views, 0) + 1
    WHERE campaign_id = :cid AND offer_id = :oid
");
$stmt->execute([
    ':cid' => $campaignId,
    ':oid' => $selectedOffer['offer_id']
]);

// ==========================================
// 5. Fetch buffer domains
// ==========================================
$stmt = db()->prepare("
    SELECT id, buffer_url
    FROM websites_buffers
    WHERE website_id = :wid
");
$stmt->execute([':wid' => $selectedOffer['website_id']]);
$buffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$buffers) {
    logRedirect($selectedOffer['offer_id'], "No buffer domains", "website_id " . $selectedOffer['website_id']);
    exit("Error: No buffer domains available");
}

// ==========================================
// 6. Select random buffer domain
// ==========================================
$rand = rand(0, count($buffers) - 1);
$selectedBuffer = $buffers[$rand];

// ==========================================
// 7. Generate click_id
// ==========================================
$clickId = uniqid('cid', true);

// ==========================================
// 8. Build affiliate link
// ==========================================
$stmt = db()->prepare("SELECT name FROM affiliate_programs WHERE id = :id");
$stmt->execute([':id' => $selectedOffer['affiliate_program_id']]);
$program = strtolower($stmt->fetchColumn() ?: 'default');

function buildCustomAffiliateUrl($affiliatelink, $program, $clickId) {
    switch (strtolower($program)) {
        case 'oponia':
            $token = "publisherId=";
            break;
        case 'yieldkit':
            $token = "yk_tag=";
            break;
        default:
            $token = "subid=";
            break;
    }
    return $affiliatelink . (strpos($affiliatelink, '?') === false ? '?' : '&') . $token . urlencode($clickId);
}

$customAffiliateUrl = buildCustomAffiliateUrl(
    $selectedOffer['affiliate_link'],
    $program,
    $clickId
);

// ==========================================
// 9. Set cookies for root domain
// ==========================================
//setcookie('buffer_url', $selectedBuffer['buffer_url'], time() + 10, "/", ".wisemindvibe.com", true, true);
setcookie('affiliate_url', $customAffiliateUrl, time() + 10, "/", ".wisemindvibe.com", true, true);
//setcookie('visit_flag1', '1', time() + 10, "/", ".wisemindvibe.com", true, true);

// ==========================================
// 10. Log click
// ==========================================
$stmt = db()->prepare("
    INSERT INTO clicks 
        (click_id, offer_id, campaign_id, country, OS, browser, zone_id, cost, payout, ip)
    VALUES 
        (:click_id, :offer_id, :campaign_id, :country, :os, :browser, :zone_id, :cost, 0, INET6_ATON(:ip))
");
$stmt->execute([
    ':click_id' => $clickId,
    ':offer_id' => $selectedOffer['offer_id'],
    ':campaign_id' => $campaignId,
    ':country' => $country,
    ':os' => $os,
    ':browser' => $browser,
    ':zone_id' => $zone_id,
    ':cost' => $cost,
    ':ip' => $ip
]);


// ==========================================
// 11. Redirect to root domain
// ==========================================
$stmt = db()->prepare("SELECT domain FROM websites WHERE id = :wid LIMIT 1");
$stmt->execute([':wid' => $selectedOffer['website_id']]);
$websiteDomain = $stmt->fetchColumn();

if (!$websiteDomain) exit("Website domain not found");

// Redirect to root domain
//header("Location: https://" . $selectedBuffer['buffer_url']);
header("Location: " . $customAffiliateUrl);
exit;



