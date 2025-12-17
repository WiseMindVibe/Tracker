<?php
// redirect.php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/functions/log_redirect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==========================================
// DEBUG FUNCTION (ensures logs folder exists)
// ==========================================
function debugLog($message, $data = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logfile = $logDir . '/redirect_debug.log';
    $entry = date('Y-m-d H:i:s') . " | $message";
    if ($data !== null) {
        // avoid json_encode failure on resources
        $entry .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    @file_put_contents($logfile, $entry . PHP_EOL, FILE_APPEND);
}

// Helper - safe redirect and exit
function safeRedirect($url) {
    header("Location: " . $url);
    exit;
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
$cost       = isset($_GET['cost']) ? floatval($_GET['cost']) : 0.1;
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

debugLog("GET parameters received", $_GET);

// Basic validation
if (!$campaignId) {
    debugLog("Missing or invalid campaign id", $campaignId);
    logRedirect($campaignId, "Missing campaign id", null);
    safeRedirect('https://google.com');
}

// ==========================================
// 2. Fetch campaign
// ==========================================
try {
    $stmt = db()->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    debugLog("DB error fetching campaign", $e->getMessage());
    logRedirect($campaignId, "DB error fetching campaign", $e->getMessage());
    safeRedirect('https://google.com');
}

if (!$campaign) {
    debugLog("Campaign not found", $campaignId);
    logRedirect($campaignId, "Campaign not found", null);
    safeRedirect('https://google.com');
}

// ==========================================
// 3. Load countries JSON (expects $countries & $countryAliases in bootstrap)
// ==========================================
$path = __DIR__ . '/../data/countries.json';
if (!file_exists($path)) {
    debugLog("Countries JSON file missing", $path);
    logRedirect($campaignId, "Countries JSON file not found", $path);
    safeRedirect('https://google.com');
}

if (!isset($countries) || !is_array($countries)) {
    // attempt to load if not provided by bootstrap
    $raw = file_get_contents($path);
    $countries = json_decode($raw, true) ?: [];
}

if (!isset($countryAliases) || !is_array($countryAliases)) {
    $countryAliases = []; // fallback
}

// Map campaign country code
$campaignCountryCode = strtolower(trim($campaign['country'] ?? ''));

$countryCodes = array_map(fn($c) => strtolower($c['code'] ?? ''), $countries);

if ($campaignCountryCode !== '' && !in_array($campaignCountryCode, $countryCodes)) {
    debugLog("Campaign country code not in countries.json", $campaign['country']);
    logRedirect($campaignId, "Campaign country code not found", $campaign['country']);
    safeRedirect('https://google.com');
}

debugLog("Mapped campaign country code", $campaignCountryCode);

// ==========================================
// 4. Compare visitor country code
// ==========================================
$visitorCountryCode = strtolower(trim($country ?? ''));

// Use aliases if available
$allowedCountryCodes = array_map('strtolower', $countryAliases[$campaignCountryCode] ?? [$campaignCountryCode]);

if ($campaignCountryCode !== '' && !in_array($visitorCountryCode, $allowedCountryCodes)) {
    debugLog("Visitor country mismatch", ['visitor'=>$visitorCountryCode,'campaign'=>$campaignCountryCode,'allowed'=>$allowedCountryCodes]);
    logRedirect($campaignId, "Visitor country mismatch", "Campaign: $campaignCountryCode | Visitor: $visitorCountryCode");
    safeRedirect('https://google.com');
}

debugLog("Visitor country code matched", ['visitor'=>$visitorCountryCode]);

// ==========================================
// 5. Fetch campaign offers
// ==========================================
try {
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
} catch (Throwable $e) {
    debugLog("DB error fetching campaign offers", $e->getMessage());
    logRedirect($campaignId, "DB error fetching campaign offers", $e->getMessage());
    safeRedirect('https://google.com');
}

if (!$offers) {
    debugLog("No offers found in campaign", $campaignId);
    logRedirect($campaignId, "No offers in campaign", null);
    safeRedirect('https://google.com');
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

    // ==========================================
    // Fetch traffic_source_id + external_campaign_id(s)
    // from campaign_external_ids table
    // ==========================================
    try {
        $stmt = db()->prepare("
            SELECT traffic_source_id, external_campaign_id
            FROM campaign_external_ids
            WHERE campaign_id = :cid
        ");
        $stmt->execute([':cid' => $campaignId]);
        $campaignData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        debugLog("DB error fetching campaign external ids", $e->getMessage());
        logRedirect($campaignId, "DB error fetching campaign external ids", $e->getMessage());
        safeRedirect('https://google.com');
    }

    if (empty($campaignData)) {
        debugLog("No external campaign ids found", $campaignId);
        logRedirect($campaignId, "No external campaign ids found", null);
        safeRedirect('https://google.com');
    }

    foreach ($campaignData as $c) {
        $tsId = $c['traffic_source_id'] ?? null;
        $extIdRaw = $c['external_campaign_id'] ?? null;

        if (empty($tsId) || empty($extIdRaw)) {
            debugLog("Skipping stop: missing traffic source or external campaign ID", $c);
            continue;
        }

        // Normalize external IDs to integer array
        $extIdsArray = [];
        if (is_string($extIdRaw)) {
            $extIdTrim = trim($extIdRaw);
            // comma separated
            if (strpos($extIdTrim, ',') !== false) {
                $parts = array_filter(array_map('trim', explode(',', $extIdTrim)), fn($v)=>$v!=='' );
                $extIdsArray = array_map('intval', $parts);
            }
            // JSON array string
            elseif (preg_match('/^\[.*\]$/', $extIdTrim)) {
                $decoded = json_decode($extIdTrim, true);
                if (is_array($decoded)) {
                    $extIdsArray = array_map('intval', $decoded);
                } else {
                    $extIdsArray = [(int)$extIdTrim];
                }
            }
            else {
                // single id string
                $extIdsArray = [(int)$extIdTrim];
            }
        } elseif (is_array($extIdRaw)) {
            $extIdsArray = array_map('intval', $extIdRaw);
        } else {
            $extIdsArray = [(int)$extIdRaw];
        }

        // Remove falsy zeros (in case intval produced 0 for non-numeric)
        $extIdsArray = array_values(array_filter($extIdsArray, fn($v)=>$v > 0));

        if (empty($extIdsArray)) {
            debugLog("No valid external campaign ids after normalization", $c);
            continue;
        }

        // Call the debug-ready stop function which returns detailed response
        // (assumes stopTrafficSourceCampaign returns an array with 'success','details','response','http_code')
        $result = stopTrafficSourceCampaign($tsId, $extIdsArray);

        debugLog("Traffic source campaign pause attempt", [
            'traffic_source_id' => $tsId,
            'external_campaign_id' => $extIdsArray,
            'result' => $result
        ]);

        // Log to redirect_logs table for visibility
        logRedirect($campaignId, "Request sent to traffic source", $result['details'] ?? $result);

        if (is_array($result) && isset($result['success']) && $result['success']) {
            // optional: update local campaign state if you want (e.g., mark campaign paused)
            debugLog("Stop request succeeded for external ids", $extIdsArray);
        } else {
            debugLog("Stop request returned failure or partial failure", $result);
        }
    }

    // After processing stops, fallback safely
    safeRedirect('https://google.com');
}

// ==========================================
// 6. Select offer (there is available offer)
// ==========================================
usort($availableOffers, fn($a,$b)=> $a['fill_percent'] <=> $b['fill_percent']);
$halfIndex = ceil(count($availableOffers)/2);
$topOffers = array_slice($availableOffers,0,$halfIndex);
$selectedOffer = $topOffers[array_rand($topOffers)];

try {
    $stmt = db()->prepare("
        UPDATE campaign_offers
        SET current_views = COALESCE(current_views,0)+1
        WHERE campaign_id=:cid AND offer_id=:oid
    ");
    $stmt->execute([':cid'=>$campaignId,':oid'=>$selectedOffer['offer_id']]);
} catch (Throwable $e) {
    debugLog("DB error updating campaign_offers current_views", $e->getMessage());
    // continue anyway
}


// ==========================================
// 7. Fetch buffer domain
// ==========================================
try {
    $stmt = db()->prepare("SELECT id, buffer_url FROM websites_buffers WHERE website_id=:wid");
    $stmt->execute([':wid'=>$selectedOffer['website_id']]);
    $buffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    logRedirect($campaignId, "DB error fetching buffers", $e->getMessage());
    safeRedirect('https://google.com');
}

if (!$buffers) {
    logRedirect($selectedOffer['offer_id'], "No buffer domains", "website_id " . $selectedOffer['website_id']);
    safeRedirect('https://google.com');
}

$selectedBuffer = $buffers[array_rand($buffers)];

// ==========================================
// 8. Generate click_id and affiliate URL
// ==========================================
$clickId = uniqid('cid', true);

try {
    $stmt = db()->prepare("SELECT name FROM affiliate_programs WHERE id=:id");
    $stmt->execute([':id'=>$selectedOffer['affiliate_program_id']]);
    $program = strtolower($stmt->fetchColumn() ?: 'default');
} catch (Throwable $e) {
    $program = 'default';
}

function buildCustomAffiliateUrl($affiliatelink,$program,$clickId){
    switch(strtolower($program)){
        case 'oponia': return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."publisherId=".urlencode($clickId);
        case 'yieldkit': return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."yk_tag=".urlencode($clickId);
        default: return $affiliatelink . (strpos($affiliatelink,'?')===false?'?':'&')."subid=".urlencode($clickId);
    }
}

$customAffiliateUrl = buildCustomAffiliateUrl($selectedOffer['affiliate_link'],$program,$clickId);

// ==========================================
// 9. Set cookies (short-lived for tracking)
// ==========================================
setcookie('affiliate_url', $customAffiliateUrl, time()+10, "/", ".wisemindvibe.com", true, true);

// ==========================================
// 10. Log click
// ==========================================
try {
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
} catch (Throwable $e) {
    debugLog("DB error inserting click", $e->getMessage());
    // continue - do not block redirect
}


// ==========================================
// 11. Final redirect to affiliate
// ==========================================
safeRedirect($customAffiliateUrl);