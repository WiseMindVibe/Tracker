<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/functions/log_redirect.php';

// ==========================================
// DEBUG FUNCTION
// ==========================================
function debugLog($message, $data = null) {
    $logfile = __DIR__ . '/../logs/redirect_debug.log';
    $entry = date('Y-m-d H:i:s') . " | $message";
    if ($data !== null) $entry .= " | " . json_encode($data);
    file_put_contents($logfile, $entry . PHP_EOL, FILE_APPEND);
}

// ==========================================
// 1. Extract GET parameters
// ==========================================
$clickid    = $_GET['SUBID'] ?? null;
$campaignId = isset($_GET['cid']) ? intval($_GET['cid']) : null;
$country    = $_GET['country'] ?? null;
$os         = $_GET['os'] ?? 'Unknown';
$browser    = $_GET['browser'] ?? 'Unknown';
$connection_type = $_GET['connection_type'] ?? 'Unknown';
$isp        = $_GET['isp'] ?? 'Unknown';
$carrier    = $_GET['carrier'] ?? 'Unknown';
$zone_id    = isset($_GET['zoneid']) ? intval($_GET['zoneid']) : 'Unknown';
$cost       = isset($_GET['cost']) ? floatval($_GET['cost']) : 'Unknown';
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

debugLog("GET parameters received", $_GET);

// ==========================================
// 2. Validate campaign exists
// ==========================================
$stmt = db()->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $campaignId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    debugLog("Campaign not found", $campaignId);
    logRedirect($campaignId, "Campaign not found", null);
    header("Location: https://google.com");
    exit;
}

// ==========================================
// 3. Load countries JSON and map campaign country
// ==========================================
$path = __DIR__ . '/../data/countries.json';
if (!file_exists($path)) {
    debugLog("Countries JSON file missing", $path);
    logRedirect($campaignId, "Countries JSON file not found", $path);
    header("Location: https://google.com");
    exit;
}

if (!$countries) {
    debugLog("Failed to decode countries JSON", $path);
    logRedirect($campaignId, "Failed to decode countries JSON", $path);
    header("Location: https://google.com");
    exit;
}

// Map campaign country code to name
$campaignCountryCode = strtoupper(trim($campaign['country'] ?? ''));
$campaignCountryName = null;

foreach ($countries as $c) {
    if (strtoupper($c['code']) === $campaignCountryCode) {
        $campaignCountryName = strtolower(trim($c['name']));
        break;
    }
}

if (!$campaignCountryName) {
    debugLog("Campaign country code not found in countries.json", $campaign['country']);
    logRedirect($campaignId, "Campaign country code not found", $campaign['country']);
    header("Location: https://google.com");
    exit;
}

debugLog("Mapped campaign country", ['code'=>$campaignCountryCode,'name'=>$campaignCountryName]);

// ==========================================
// 4. Compare visitor country
// ==========================================
$visitorCountry = strtolower(trim($country ?? ''));


$allowedCountries = $countryAliases[$campaignCountryCode] ?? [$campaignCountryName];

if (!in_array($visitorCountry, array_map('strtolower', $allowedCountries))) {
    debugLog("Visitor country mismatch", ['visitor'=>$visitorCountry,'campaign'=>$campaignCountryName,'allowed'=>$allowedCountries]);
    logRedirect($campaignId, "Visitor country mismatch", "Campaign: $campaignCountryName | Visitor: $visitorCountry");
    header("Location: https://google.com");
    exit;
}

debugLog("Visitor country matched", ['visitor'=>$visitorCountry]);

// ==========================================
// 5. Fetch campaign offers
// ==========================================
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
    debugLog("No offers found in campaign", $campaignId);
    logRedirect($campaignId, "No offers in campaign", null);
    header("Location: https://google.com");
    exit;
}

$availableOffers = [];
$allCapped = true;

foreach ($offers as $offer) {
    $views = (int)($offer['current_views'] ?? 0);
    $cap = (int)$offer['cap'];
    if ($views < $cap) {
        $allCapped = false;
        $offer['current_views'] = $views;
        $offer['fill_percent'] = $views / max($cap,1);
        $availableOffers[] = $offer;
    }
}

if ($allCapped) {
    debugLog("All offers capped", $campaignId);
    logRedirect($campaignId, "Campaign capped", "campaign capped");

    // Fetch all traffic_source_id + external_campaign_id for this campaign
    $stmt = db()->prepare("
        SELECT traffic_source_id, external_campaign_id 
        FROM campaigns 
        WHERE id = :cid
    ");
    $stmt->execute([':cid' => $campaignId]);
    $campaignData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($campaignData as $c) {
        $tsId = $c['traffic_source_id'];
        $extId = $c['external_campaign_id'];

        if ($tsId && $extId) {
            $paused = stopTrafficSourceCampaign($tsId, $extId);
            debugLog("Traffic source campaign pause attempt", [
                'traffic_source_id' => $tsId,
                'external_campaign_id' => $extId,
                'paused' => $paused
            ]);
        }
    }

    // Redirect backup
    header("Location: https://google.com");
    exit;
}



// ==========================================
// 6. Select offer
// ==========================================
usort($availableOffers, fn($a,$b)=> $a['fill_percent'] <=> $b['fill_percent']);
$halfIndex = ceil(count($availableOffers)/2);
$topOffers = array_slice($availableOffers,0,$halfIndex);
$selectedOffer = $topOffers[array_rand($topOffers)];

// Increment views
$stmt = db()->prepare("
    UPDATE campaign_offers
    SET current_views = COALESCE(current_views,0)+1
    WHERE campaign_id=:cid AND offer_id=:oid
");
$stmt->execute([':cid'=>$campaignId,':oid'=>$selectedOffer['offer_id']]);
debugLog("Offer selected and view incremented", $selectedOffer);

// ==========================================
// 7. Fetch buffer domain
// ==========================================
$stmt = db()->prepare("SELECT id, buffer_url FROM websites_buffers WHERE website_id=:wid");
$stmt->execute([':wid'=>$selectedOffer['website_id']]);
$buffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$buffers) {
    debugLog("No buffer domains", $selectedOffer['website_id']);
    logRedirect($selectedOffer['offer_id'], "No buffer domains", "website_id " . $selectedOffer['website_id']);
    exit("Error: No buffer domains available");
}

$selectedBuffer = $buffers[rand(0,count($buffers)-1)];
debugLog("Buffer selected", $selectedBuffer);

// ==========================================
// 8. Generate click_id and affiliate URL
// ==========================================
$clickId = uniqid('cid', true);

$stmt = db()->prepare("SELECT name FROM affiliate_programs WHERE id=:id");
$stmt->execute([':id'=>$selectedOffer['affiliate_program_id']]);
$program = strtolower($stmt->fetchColumn() ?: 'default');

function buildCustomAffiliateUrl($affiliatelink,$program,$clickId){
    switch(strtolower($program)){
        case 'oponia': return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."publisherId=".urlencode($clickId);
        case 'yieldkit': return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."yk_tag=".urlencode($clickId);
        default: return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."subid=".urlencode($clickId);
    }
}

$customAffiliateUrl = buildCustomAffiliateUrl($selectedOffer['affiliate_link'],$program,$clickId);
debugLog("Affiliate URL generated",$customAffiliateUrl);

// ==========================================
// 9. Set cookies
// ==========================================
setcookie('affiliate_url', $customAffiliateUrl, time()+10, "/", ".wisemindvibe.com", true, true);

// ==========================================
// 10. Log click
// ==========================================
$stmt = db()->prepare("
    INSERT INTO clicks 
    (click_id, offer_id, campaign_id, country, OS, browser, zone_id, cost, payout, ip)
    VALUES
    (:click_id,:offer_id,:campaign_id,:country,:os,:browser,:zone_id,:cost,0,INET6_ATON(:ip))
");
$stmt->execute([
    ':click_id'=>$clickId,
    ':offer_id'=>$selectedOffer['offer_id'],
    ':campaign_id'=>$campaignId,
    ':country'=>$country,
    ':os'=>$os,
    ':browser'=>$browser,
    ':zone_id'=>$zone_id,
    ':cost'=>$cost,
    ':ip'=>$ip
]);

debugLog("Click logged",$clickId);

// ==========================================
// 11. Redirect
// ==========================================
debugLog("Redirecting to affiliate URL",$customAffiliateUrl);
header("Location: ".$customAffiliateUrl);
exit;
