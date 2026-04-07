<?php

/**
 * Campaign redirect: validate campaign + geo, pick least-filled eligible offer,
 * log click, redirect via buffer URL when available.
 *
 * Buffers: TRACKER_DEFAULT_BUFFER_URL in .env (if set) is always used as the only buffer hop
 * (?tc= click token). websites_buffers for the offer's website_id is used only when that env
 * is unset. Prevents bad DB rows (e.g. network URLs) from skipping your site.
 *
 * @see src/functions/tracker_logs.php — redirect_logs
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/functions/traffic_source_campaign.php';
require_once __DIR__ . '/../src/functions/affiliate_offer_url.php';

function safeRedirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Buffer URLs stored without a scheme (e.g. t.co/abc) must be absolute or browsers treat them as paths on the tracker host.
 */
function normalizeBufferBaseUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return $url;
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }

    return 'https://' . ltrim($url, '/');
}

/**
 * Buffer landing page receives only `tc` (clicks.click_id). Resolve to the offer via public/go.php (same DB).
 * If buffer_url contains {url}, it is replaced with rawurlencode($fullAffiliateUrl) (legacy / full-URL-in-query).
 */
function buildBufferRedirectUrl(string $bufferBaseUrl, string $clickId, string $fullAffiliateUrl): string
{
    $bufferBaseUrl = trim($bufferBaseUrl);
    if (strpos($bufferBaseUrl, '{url}') !== false) {
        $out = str_replace('{url}', rawurlencode($fullAffiliateUrl), $bufferBaseUrl);

        return normalizeBufferBaseUrl($out);
    }

    $base = rtrim(normalizeBufferBaseUrl($bufferBaseUrl), '/');
    $sep = strpos($base, '?') !== false ? '&' : '?';

    return $base . $sep . 'tc=' . rawurlencode($clickId);
}

function offerCountryMatchesVisitor(?string $offerCountry, string $visitorCountryCode): bool
{
    $oc = strtolower(trim((string) $offerCountry));
    if ($oc === '') {
        return true;
    }

    return $oc === $visitorCountryCode;
}

function setAffiliateUrlCookie(string $customAffiliateUrl): void
{
    $domain = getenv('TRACKER_COOKIE_DOMAIN');
    $exp = time() + 86400;
    if (PHP_VERSION_ID >= 70300) {
        $opts = [
            'expires' => $exp,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== false && $domain !== '') {
            $opts['domain'] = $domain;
        }
        setcookie('affiliate_url', $customAffiliateUrl, $opts);
        return;
    }
    $dom = ($domain !== false && $domain !== '') ? $domain : '';
    setcookie('affiliate_url', $customAffiliateUrl, $exp, '/', $dom, true, true);
}

// --- params ---
$internalCampaignId = $_GET['cid'] ?? $_GET['camid'] ?? null;
$externalCampaignIdParam = $_GET['campaign_id'] ?? null;
$providedSubid = isset($_GET['SUBID']) ? trim((string) $_GET['SUBID']) : '';

$visitor = [
    'country' => $_GET['country'] ?? null,
    'region' => $_GET['region'] ?? null,
    'language' => $_GET['language'] ?? null,
    'device' => $_GET['device'] ?? 'Unknown',
    'os' => $_GET['os'] ?? 'Unknown',
    'os_version' => $_GET['os_version'] ?? 'Unknown',
    'browser' => $_GET['browser'] ?? 'Unknown',
    'browser_version' => $_GET['browser_version'] ?? 'Unknown',
    'connection_type' => $_GET['connection_type'] ?? 'Unknown',
    'isp' => $_GET['isp'] ?? 'Unknown',
    'carrier' => $_GET['carrier'] ?? 'Unknown',
    'zone_id' => isset($_GET['zoneid']) ? ($_GET['zoneid'] === '' ? null : $_GET['zoneid']) : null,
    'subzone_ide_id' => isset($_GET['subzone_id']) ? ($_GET['subzone_id'] === '' ? null : $_GET['subzone_id']) : null,
    'user_agent' => $_GET['useragent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'),
    'user_activity' => $_GET['user_activity'] ?? null,
    'banner_id' => $_GET['banner_id'] ?? null,
    'cost' => isset($_GET['cost']) ? floatval($_GET['cost']) : 0.1,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
];

$externalCampaignIdParam = $externalCampaignIdParam === null ? null : trim((string) $externalCampaignIdParam);

if (empty($internalCampaignId)) {
    tracker_redirect_log('missing_campaign_id', 'No internal campaign id (cid/camid) provided', '', ['source_subid' => $providedSubid ?: null]);
    safeRedirect('https://google.com');
}

if (!preg_match('/^[0-9a-fA-F\-]{4,64}$/', $internalCampaignId)) {
    tracker_redirect_log('invalid_campaign_id', 'internal campaign id failed basic pattern check', (string) $internalCampaignId);
    safeRedirect('https://google.com');
}

$countriesPath = __DIR__ . '/../data/countries.json';
if (!is_file($countriesPath)) {
    $countriesPath = __DIR__ . '/../assets/includes/countries.json';
}
$countryAliases = [];
$countries = [];
if (is_file($countriesPath)) {
    $raw = file_get_contents($countriesPath);
    $countries = json_decode($raw, true) ?: [];
}

function allowedCountryCodesFor(string $campaignCountryCode, array $countries, array $countryAliases = []): array
{
    $campaignCountryCode = strtolower(trim($campaignCountryCode));
    if ($campaignCountryCode === '') {
        return [];
    }
    if (!empty($countryAliases[$campaignCountryCode]) && is_array($countryAliases[$campaignCountryCode])) {
        return array_map('strtolower', $countryAliases[$campaignCountryCode]);
    }
    $countryCodes = array_map(fn ($c) => strtolower($c['code'] ?? ''), $countries);
    if (in_array($campaignCountryCode, $countryCodes, true)) {
        return [$campaignCountryCode];
    }

    return [];
}

try {
    $stmt = db()->prepare('SELECT * FROM campaigns WHERE uuid = :uuid LIMIT 1');
    $stmt->execute([':uuid' => $internalCampaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    tracker_redirect_log('db_error_fetch_campaign', $e->getMessage(), (string) $internalCampaignId, null, 'error');
    safeRedirect('https://google.com');
}

if (!$campaign) {
    tracker_redirect_log('campaign_not_found', 'No campaign row found for provided id', (string) $internalCampaignId);
    safeRedirect('https://google.com');
}

$campaignUuid = (string) ($campaign['uuid'] ?? '');
$campaignInternalId = (int) ($campaign['id'] ?? 0);

$campaignCountryCode = strtolower(trim((string) ($campaign['country'] ?? '')));
$countryCodesAllowed = [];
if ($campaignCountryCode !== '') {
    $countryCodesAllowed = allowedCountryCodesFor($campaignCountryCode, $countries, $countryAliases);
    if ($countryCodesAllowed === []) {
        tracker_redirect_log('campaign_country_unknown', "Campaign country '{$campaign['country']}' not present in countries list", $campaignUuid);
        safeRedirect('https://google.com');
    }
}

$visitorCountryCode = strtolower(trim((string) ($visitor['country'] ?? '')));

if ($campaignCountryCode !== '' && !in_array($visitorCountryCode, $countryCodesAllowed, true)) {
    $reason = "Campaign: {$campaignCountryCode} | Visitor: {$visitorCountryCode}";
    tracker_redirect_log('country_mismatch', $reason, $campaignUuid);
    try {
        $impressionClickId = uniqid('imp', true);
        $stmtImp = db()->prepare('
            INSERT INTO clicks (click_id, offer_id, campaign_id, external_campaign_id, country, OS, browser, zone_id, subzone_ide_id, cost, ip, user_agent, connection_type, isp, carrier, banner_id, created_at)
            VALUES (:click_id, NULL, :campaign_id, :external_campaign_id, :country, :os, :browser, :zone_id, :subzone_ide_id, :cost, INET6_ATON(:ip), :user_agent, :connection_type, :isp, :carrier, :banner_id, NOW())
        ');
        $stmtImp->execute([
            ':click_id' => $impressionClickId,
            ':campaign_id' => $campaignUuid,
            ':external_campaign_id' => $externalCampaignIdParam,
            ':country' => $visitor['country'],
            ':os' => $visitor['os'],
            ':browser' => $visitor['browser'],
            ':zone_id' => $visitor['zone_id'],
            ':subzone_ide_id' => $visitor['subzone_ide_id'],
            ':cost' => 0.0,
            ':ip' => $visitor['ip'],
            ':user_agent' => $visitor['user_agent'],
            ':connection_type' => $visitor['connection_type'],
            ':isp' => $visitor['isp'],
            ':carrier' => $visitor['carrier'],
            ':banner_id' => $visitor['banner_id'],
        ]);
    } catch (Throwable $e) {
        tracker_redirect_log('impression_insert_failed', $e->getMessage(), $campaignUuid, null, 'warning');
    }
    safeRedirect('https://google.com');
}

try {
    $stmt = db()->prepare('
        SELECT
            co.offer_id,
            COALESCE(co.cap, 0) AS cap,
            COALESCE(co.current_views, 0) AS current_views,
            o.name AS offer_name,
            o.affiliate_link,
            o.country AS offer_country,
            o.affiliate_program_id,
            o.website_id
        FROM campaign_offers co
        JOIN offers o ON o.id = co.offer_id
        WHERE co.campaign_id = :cid
    ');
    $stmt->execute([':cid' => $campaignInternalId]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    tracker_redirect_log('db_error_fetch_offers', $e->getMessage(), $campaignUuid, null, 'error');
    safeRedirect('https://google.com');
}

if ($offers === []) {
    tracker_redirect_log('no_offers', 'Campaign has no offers associated', $campaignUuid);
    safeRedirect('https://google.com');
}

$offerList = [];
foreach ($offers as $offer) {
    $cap = (int) $offer['cap'];
    $views = (int) $offer['current_views'];
    $fillPercent = ($cap > 0) ? ($views / $cap) : 1.0;
    $offer['fill_percent'] = $fillPercent;
    $offer['cap'] = $cap;
    $offer['current_views'] = $views;
    $offerList[] = $offer;
}

$eligibleOffers = [];
foreach ($offerList as $o) {
    if ($o['cap'] <= 0 || $o['current_views'] >= $o['cap']) {
        continue;
    }
    if (!offerCountryMatchesVisitor($o['offer_country'] ?? null, $visitorCountryCode)) {
        continue;
    }
    $eligibleOffers[] = $o;
}

if ($eligibleOffers === []) {
    try {
        $stmt = db()->prepare('SELECT external_campaign_id FROM campaign_external_ids WHERE campaign_id = :cid');
        $stmt->execute([':cid' => $campaignInternalId]);
        $externalRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        tracker_redirect_log('db_error_fetch_external_ids', $e->getMessage(), $campaignUuid, null, 'error');
        safeRedirect('https://google.com');
    }

    $tsId = (int) ($campaign['traffic_source_id'] ?? 0);

    if ($externalRows === []) {
        tracker_redirect_log('no_external_ids_found', 'No external campaign ids attached; all offers capped or filtered', $campaignUuid);
    } elseif ($tsId < 1) {
        tracker_redirect_log('no_traffic_source', 'Campaign has no traffic_source_id for API stop', $campaignUuid);
    } else {
        foreach ($externalRows as $row) {
            $extRaw = $row['external_campaign_id'] ?? null;
            if (empty($extRaw)) {
                continue;
            }
            $extIds = normalizeExternalIds($extRaw);
            if ($extIds === []) {
                continue;
            }
            $res = stopTrafficSourceCampaign($tsId, $extIds, $campaignInternalId);
            tracker_redirect_log(
                'stop_request_sent',
                'traffic API: ' . ($res['traffic_source'] ?? 'unknown') . ' success=' . (($res['success'] ?? false) ? '1' : '0'),
                $campaignUuid,
                ['http_code' => $res['http_code'] ?? null]
            );
        }
    }

    tracker_redirect_log('all_offers_capped_or_filtered', 'No eligible offers (caps / offer-country)', $campaignUuid);
    safeRedirect('https://google.com');
}

usort($eligibleOffers, static function ($a, $b) {
    if ($a['fill_percent'] == $b['fill_percent']) {
        return 0;
    }

    return ($a['fill_percent'] < $b['fill_percent']) ? -1 : 1;
});

$lowestFill = $eligibleOffers[0]['fill_percent'];
$lowestGroup = array_values(array_filter($eligibleOffers, static fn ($x) => $x['fill_percent'] === $lowestFill));
$selectedOffer = count($lowestGroup) > 1 ? $lowestGroup[array_rand($lowestGroup)] : $eligibleOffers[0];

try {
    $stmt = db()->prepare('
        UPDATE campaign_offers
        SET current_views = COALESCE(current_views,0)+1
        WHERE campaign_id = :cid AND offer_id = :oid
    ');
    $stmt->execute([
        ':cid' => $campaignInternalId,
        ':oid' => $selectedOffer['offer_id'],
    ]);
} catch (Throwable $e) {
    tracker_redirect_log('warning_update_views_failed', $e->getMessage(), $campaignUuid, null, 'warning');
}

$buffers = [];
$dbBufferCount = 0;
try {
    $wid = $selectedOffer['website_id'] ?? null;
    if ($wid !== null && $wid !== '' && (int) $wid > 0) {
        $stmt = db()->prepare('SELECT id, buffer_url FROM websites_buffers WHERE website_id = :wid');
        $stmt->execute([':wid' => (int) $wid]);
        $buffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $dbBufferCount = count($buffers);
} catch (Throwable $e) {
    tracker_redirect_log('db_error_fetch_buffers', $e->getMessage(), $campaignUuid, null, 'error');
}

$defaultBufferUrl = trim((string) (getenv('TRACKER_DEFAULT_BUFFER_URL') ?: ''));
if ($defaultBufferUrl !== '') {
    if ($dbBufferCount > 0) {
        tracker_redirect_log(
            'default_buffer_overrides_db',
            'TRACKER_DEFAULT_BUFFER_URL takes precedence over websites_buffers',
            $campaignUuid,
            ['db_buffer_count' => $dbBufferCount, 'website_id' => $selectedOffer['website_id'] ?? null]
        );
    } else {
        tracker_redirect_log('default_buffer_used', 'Using TRACKER_DEFAULT_BUFFER_URL (no DB buffers for this offer)', $campaignUuid, ['website_id' => $selectedOffer['website_id'] ?? null]);
    }
    $buffers = [['id' => 0, 'buffer_url' => $defaultBufferUrl]];
}

$clickId = uniqid('cid', true);
$programName = affiliateProgramSlugFromAccountId((int) $selectedOffer['affiliate_program_id']);
$customAffiliateUrl = buildCustomAffiliateUrl($selectedOffer['affiliate_link'], $programName, $clickId);

$clickInserted = false;
try {
    $stmt = db()->prepare('
        INSERT INTO clicks
        (click_id, offer_id, campaign_id, external_campaign_id, country, region, OS, browser, zone_id, subzone_ide_id, cost, ip, user_agent, connection_type, isp, carrier, banner_id, created_at)
        VALUES
        (:click_id, :offer_id, :campaign_id, :external_campaign_id, :country, :region, :os, :browser, :zone_id, :subzone_ide_id, :cost, INET6_ATON(:ip), :user_agent, :connection_type, :isp, :carrier, :banner_id, NOW())
    ');
    $stmt->execute([
        ':click_id' => $clickId,
        ':offer_id' => $selectedOffer['offer_id'],
        ':campaign_id' => $campaignUuid,
        ':external_campaign_id' => $externalCampaignIdParam,
        ':country' => $visitor['country'],
        ':region' => $visitor['region'],
        ':os' => $visitor['os'],
        ':browser' => $visitor['browser'],
        ':zone_id' => $visitor['zone_id'],
        ':subzone_ide_id' => $visitor['subzone_ide_id'],
        ':cost' => $visitor['cost'],
        ':ip' => $visitor['ip'],
        ':user_agent' => $visitor['user_agent'],
        ':connection_type' => $visitor['connection_type'],
        ':isp' => $visitor['isp'],
        ':carrier' => $visitor['carrier'],
        ':banner_id' => $visitor['banner_id'],
    ]);
    $clickInserted = true;
} catch (Throwable $e) {
    tracker_redirect_log('db_error_insert_click', $e->getMessage(), $campaignUuid, null, 'error');
}

$destUrl = $customAffiliateUrl;
if ($buffers !== [] && $clickInserted) {
    $selectedBuffer = $buffers[array_rand($buffers)];
    $destUrl = buildBufferRedirectUrl((string) $selectedBuffer['buffer_url'], $clickId, $customAffiliateUrl);
    tracker_redirect_log(
        'served_offer',
        "offer_id: {$selectedOffer['offer_id']} via buffer",
        $campaignUuid,
        ['offer_id' => $selectedOffer['offer_id'], 'buffer_id' => $selectedBuffer['id'] ?? null, 'traffic_subid' => $providedSubid ?: null]
    );
} elseif ($buffers !== [] && !$clickInserted) {
    tracker_redirect_log('buffer_skipped_click_failed', 'Falling back to direct offer URL', $campaignUuid, null, 'warning');
} elseif ($buffers === []) {
    setAffiliateUrlCookie($customAffiliateUrl);
    tracker_redirect_log(
        'served_offer_direct',
        "offer_id: {$selectedOffer['offer_id']} (no buffer domains)",
        $campaignUuid,
        ['offer_id' => $selectedOffer['offer_id'], 'traffic_subid' => $providedSubid ?: null],
        'warning'
    );
}

safeRedirect($destUrl);
