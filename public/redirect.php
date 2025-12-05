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
// 3. Fetch offers in campaign/////////////////////////////////////////////////////////////////////
// ==========================================
$stmt = db()->prepare("
    SELECT 
        o.id AS offer_id,
        o.name AS offer_name,
        o.affiliate_link,
        o.country,
        o.affiliate_program_id,
        o.website_id,
        co.cap
    FROM campaign_offers co
    JOIN offers o ON o.id = co.offer_id
    WHERE co.campaign_id = :campaign_id
");
$stmt->execute([':campaign_id' => $campaignId]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$offers) {
    logRedirect($campaignId, "No offers in campaign", null);
    exit("Error: No offers in this campaign");
}

// ==========================================
// 4. Cap checking + balanced offer selection
// ==========================================

// Fetch campaign offers with cap + current_views
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

// Check which offers are still valid (not fully capped)
foreach ($offers as $offer) {
    if ($offer['current_views'] < $offer['cap']) {
        // Calculate fill %
        $offer['fill_percent'] = $offer['current_views'] / $offer['cap'];
        $availableOffers[] = $offer;
    }
}

// If no offer available => everything capped
if (empty($availableOffers)) {

    // 🔥 Placeholder: Send stop request to PropellerAds
    // replace later:
    // sendStopCampaignToPropeller($campaignId);

    logRedirect($campaignId, "All offers capped. Campaign stopped.", null);
    exit("Error: All offers capped — traffic stopped");
}

// Sort offers by lowest fill percentage
usort($availableOffers, function($a, $b) {
    return $a['fill_percent'] <=> $b['fill_percent'];
});

// Select the offer with lowest fill %
$selectedOffer = $availableOffers[0];

// ======================
// 10% Before-Cap Check
// ======================
$nearLimitThreshold = $selectedOffer['current_views'] * 1.10;

if ($nearLimitThreshold >= $selectedOffer['cap']) {
    // Redirect to fallback URL
    header("Location: https://google.com");
    exit;
}

// ==========================================
// Increment current_views for selected offer
// ==========================================
$stmt = db()->prepare("
    UPDATE campaign_offers 
    SET current_views = current_views + 1
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
// 9. Build final buffer URL
// ==========================================
$finalUrl = "https://" . $selectedBuffer['buffer_url'] . "?target_url=" . urlencode($customAffiliateUrl);

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
// 11. Redirect to buffer (t.co / fixed domain)
// ==========================================
header("Location: $finalUrl");
exit;
