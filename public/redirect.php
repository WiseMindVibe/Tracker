<?php

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/services/redirection_functions.php';

$debug = false;

$countriesPath = __DIR__ . '/../data/countries.json';
if (file_exists($countriesPath)) {
    $countriesData = file_get_contents($countriesPath);
    $countries = json_decode($countriesData, true);
} else {
    die('Countries data file not found.');
}

$params = [
    'SUB_ID' => $_GET['SUB_ID'] ?? null, //Click ID from traffic source
    'uuid' => $_GET['uuid'] ?? null, //Internal Campaign ID
    'campaign_id' => $_GET['campaign_id'] ?? null, //External Campaign ID
    'country' => strtolower($_GET['country'] ?? null),
    'language' => $_GET['language'] ?? null,
    'region' => $_GET['region'] ?? '-',
    'device' => $_GET['device'] ?? '-',
    'os' => $_GET['os'] ?? null,
    'os_version' => $_GET['os_version'] ?? '-',
    'browser' => $_GET['browser'] ?? null,
    'browser_version' => $_GET['browser_version'] ?? '-',
    'connection_type' => $_GET['connection_type'] ?? '-',
    'isp' => $_GET['isp'] ?? '-',
    'carrier' => $_GET['carrier'] ?? '-',
    'zone_id' => $_GET['zoneid'] ?? null,
    'subzone_id' => $_GET['subzone_id'] ?? '-',
    'useragent' => $_GET['useragent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '-'),
    'user_activity' => $_GET['user_activity'] ?? '-',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
    'cost' => $_GET['cost'] ?? 0.00,
    'event_id' => $_GET['event_id'] ?? null,
];

///SAFE EXIT REDIRECTION///////////////////////////////////////////////
function safeRedirection() {
    header("Location: https://google.com");
    exit;

}

/** Log traffic-source stop flow when REDIRECT_STOP_DEBUG is true/1/on (set in .env). Never logs API keys. */
function redirectStopDebugLog(string $message, array $context = []): void {
    $raw = getenv('REDIRECT_STOP_DEBUG');
    if ($raw === false || trim((string) $raw) === '') {
        return;
    }
    if (!filter_var(trim((string) $raw), FILTER_VALIDATE_BOOLEAN)) {
        return;
    }
    error_log('[REDIRECT_STOP_DEBUG] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE));
}

/**
 * @param array<string, mixed> $stopResult return value of stopTrafficSourceCampaign()
 * @return array<string, mixed>
 */
function redirectStopResultForLog(array $stopResult): array
{
    $out = [
        'success' => $stopResult['success'] ?? null,
        'reason' => $stopResult['reason'] ?? null,
        'http_code' => $stopResult['http_code'] ?? null,
        'curl_error' => $stopResult['curl_error'] ?? null,
        'traffic_source' => $stopResult['traffic_source'] ?? null,
        'source' => $stopResult['source'] ?? null,
    ];
    if (!empty($stopResult['response']) && is_string($stopResult['response'])) {
        $out['response_preview'] = strlen($stopResult['response']) > 2000
            ? substr($stopResult['response'], 0, 2000) . '…'
            : $stopResult['response'];
    }

    return $out;
}

//GENERATE UNIQUE CLICK_ID ///////////////////////////////////////
$internalClickId  = generateClickId();

//DATABASE OPERATIONS
$db = db();

//Validate campaign exists & get country
if (isset($params['uuid'])) {
    $stmt = $db->prepare("SELECT
    id, country, traffic_source_id
    FROM campaigns
    WHERE uuid = :uuid");
    $stmt->execute(['uuid' => $params['uuid']]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        if ($debug) {
            echo "ERROR - CAMPAIGN NOT FOUND for UUID: " . $params['uuid'];
            exit();
        } else {
            //LOG ERROR
            safeRedirection();
        }
    }

} else {
    if ($debug) {
        echo "ERROR - UUID MISSING";
        exit();
    } else {
        //LOG ERROR
        safeRedirection();
    }
}


//MAKE SURE CLICK COUNTRY MATCHES CAMPAIGN GEO
try {
    if (strtolower($params['country']) != strtolower($campaign['country'])) {
        if ($debug) {
            echo "GEO MISMATCH! CAMPAIGN GEO: " . $campaign['country'] . " CLICK GEO: " . $params['country'];
            exit();
        } else {
            //LOG REDIRECTION ERROR
            safeRedirection();
        }
    }
} catch (Exception $e) {
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        safeRedirection();
    }
}

//MAKE SURE THERE ARE OFFERS INSIDE THE CAMPAIGN
//IF NOT -> SEND A REQUEST TO THE TRAFFIC SOURCE TO STOP THE CAMPAIGN

//SELECT AN OFFER FROM CAMPAIGN BASED ON LOWEST PRECENTAGE
try {
    $stmt = $db->prepare("SELECT
    co.offer_id,
    co.current_views,
    co.cap
    FROM campaign_offers co
    WHERE co.campaign_id = :campaign_id
    AND co.current_views < co.cap
    ORDER BY (current_views / NULLIF(cap, 0)) ASC
    LIMIT 1");
    $stmt->execute(['campaign_id' => $campaign['id']]);

    $selectedCampaignOffer = $stmt->fetch();
} catch (Exception $e) {
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        safeRedirection();
    }
}

$stmt = $db->prepare("SELECT
        SUM(current_views) as total_views,
        SUM(cap) as total_cap
    FROM campaign_offers
    WHERE campaign_id = :campaign_id
");
$stmt->execute(['campaign_id' => $campaign['id']]);
$totals = $stmt->fetch();

$totalViews = (int) ($totals['total_views'] ?? 0);
$totalCap = (int) ($totals['total_cap'] ?? 0);
$capReached = $totalCap <= 0 || $totalViews >= $totalCap;

if ($capReached) {
    $stmtExt = $db->prepare(
        'SELECT external_campaign_id FROM campaign_external_ids WHERE campaign_id = :campaign_id'
    );
    $stmtExt->execute(['campaign_id' => $campaign['id']]);
    $externalIds = normalizeExternalIds($stmtExt->fetchAll(PDO::FETCH_COLUMN));

    $stopResult = stopTrafficSourceCampaign(
        (int) $campaign['traffic_source_id'],
        $externalIds,
        (int) $campaign['id']
    );
    redirectStopDebugLog('cap_reached stopTrafficSourceCampaign', [
        'internal_campaign_id' => (int) $campaign['id'],
        'total_views' => $totalViews,
        'total_cap' => $totalCap,
        'external_id_count' => count($externalIds),
        'stop' => redirectStopResultForLog($stopResult),
    ]);
}

if (!$selectedCampaignOffer) {
    redirectStopDebugLog('no_offer_slot', [
        'internal_campaign_id' => (int) $campaign['id'],
        'cap_reached' => $capReached,
        'total_views' => $totalViews,
        'total_cap' => $totalCap,
    ]);
    safeRedirection();
}

try {
    $stmt = $db->prepare("SELECT
    o.id,
    o.website_id,
    o.affiliate_link,
    aa.affiliate_program,
    w.domain,
    wb.buffer_url
    FROM offers o
    LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
    LEFT JOIN websites w ON w.id = o.website_id
    LEFT JOIN websites_buffers wb ON wb.website_id = w.id
    WHERE o.id = :offer_id
    ");
    $stmt->execute(['offer_id' => $selectedCampaignOffer['offer_id']]);

    $offerDetails = $stmt->fetch();

    //GET THE WEBSITE, BUFFER URL AND AFFILIATE FOR THE REDIRECTION DETAILS
    $domainBuffer = $offerDetails['buffer_url'] ?: $offerDetails['domain'];
    $affiliateLink = $offerDetails['affiliate_link'];
    $affiliateTokenKey = buildAffiliateTrackingToken($offerDetails['affiliate_program']);
    
    //BUILT AFFILIATE URL WITH THE CLICK ID AND OTHER PARAMETERS
    $fullAffiliateUrl = $affiliateLink . "&" . $affiliateTokenKey . "=" . $internalClickId ;

} catch (Exception $e) {
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        safeRedirection();
    }
}

//INCREASE CURRENT VIEWS BY 1
try {
    $stmt = $db->prepare("UPDATE campaign_offers
    SET current_views = current_views + 1
    WHERE offer_id = :offer_id
    AND campaign_id = :campaign_id
    AND current_views < cap
");
$stmt->execute([
    ':offer_id' => $offerDetails['id'],
    ':campaign_id' => $campaign['id']
]);
if ($stmt->rowCount() === 0) {
    // Someone else filled it in parallel
    safeRedirection();
}
} catch (Exception $e) {
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//INSERT INTO CLICK REDIRECTION
try {
    $stmt = $db->prepare("INSERT INTO click_redirections
    (click_id, domain, affiliate_link, affiliate_token, state)
    VALUES (:click_id, :domain, :affiliate_link, :affiliate_token, 'INIT')");

    $stmt->execute([
        ':click_id' => $internalClickId,
        ':domain' => $offerDetails['domain'],
        ':affiliate_link' => $affiliateLink,
        ':affiliate_token' => $affiliateTokenKey
    ]);
} catch (Exception $e) {
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}

//INSERT INTO CLICKS
try{
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO clicks
    (click_id,
    offer_id,
    campaign_id,
    country,
    region,
    language,
    device,
    os,
    os_version,
    browser,
    browser_version,
    connection_type,
    isp,
    carrier,
    zone_id,
    subzone_id,
    useragent,
    user_activity,
    ip,
    cost,
    event_id,
    created_at,
    updated_at)

    VALUES
    (:click_id,
    :offer_id,
    :campaign_id,
    :country,
    :region,
    :language,
    :device,
    :os,
    :os_version,
    :browser,
    :browser_version,
    :connection_type,
    :isp,
    :carrier,
    :zone_id,
    :subzone_id,
    :useragent,
    :user_activity,
    :ip,
    :cost,
    :event_id,
    NOW(),
    NOW()
    )
");

$stmt->execute([
    ':click_id' => $internalClickId,
    ':offer_id' => $offerDetails['id'],
    ':campaign_id' => $campaign['id'],
    ':country' => $params['country'],
    ':region' => $params['region'],
    ':language' => $params['language'],
    ':device' => $params['device'],
    ':os' => $params['os'],
    ':os_version' => $params['os_version'],
    ':browser' => $params['browser'],
    ':browser_version' => $params['browser_version'],
    ':connection_type' => $params['connection_type'],
    ':isp' => $params['isp'],
    ':carrier' => $params['carrier'],
    ':zone_id' => $params['zone_id'],
    ':subzone_id' => $params['subzone_id'],
    ':useragent' => $params['useragent'],
    ':user_activity' => $params['user_activity'],
    ':ip' => $params['ip'],
    ':cost' => $params['cost'],
    ':event_id' => $params['event_id']
]);

$db->commit();

} catch (Exception $e) {
    $db->rollBack();
    if ($debug) {
        echo "DB ERROR: " . $e->getMessage();
        exit();
    } else {
        //LOG ERROR
        exit("ERROR - DB ERROR");
    }
}
$secret_key = 'SECRET_KEY';
$hash = hash_hmac('sha256', $internalClickId, $secret_key);
$finalURL = "https://{$offerDetails['domain']}?click_id=" . urlencode($internalClickId) . "&hash=" . urlencode($hash);

if (!$debug) {
    header("Location: $finalURL");
    exit;
} else {
    echo "OK";
}

