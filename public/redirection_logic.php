<?php

$my_secret_key = 'SECRET_KEY'; //CHANGE LATER IN .env
$cookie_name = 'click_resume';

$buffer_url = 'http://t.co/KUibROt82H'; //CHANGE TO DYNAMIC LATER
$api_key = '1234567890'; //CHANGE LATER
$data_base = 'https://' . getenv('BASE_URL') . '/api/clicks/data.php';

$secure_cookie = isHttpsRequest();

if (!empty($_GET['click_id']) && isset($_GET['hash']) && $_GET['hash'] !== '')
{
    $click_id = urldecode($_GET['click_id']);
    $hash = urldecode($_GET['hash']);

    $expected_hash = hash_hmac('sha256', $click_id, $my_secret_key);
    if (!hash_equals($expected_hash, $hash)) {
        die('Invalid');
    }

    $data = getApiData("{$data_base}?API_KEY={$api_key}&click_id=" . rawurlencode($click_id));
    $row = normalizeClickRow($data['clickRedirectionData'] ?? null);
    if ($row === null) {
        die('Click not found');
    }

    switch ($row['state']) {
        case 'INIT':
            $new_state = 'BUFFER';
            updateClickRedirectionData($row['click_id'], $new_state, $api_key);
            setResumeCookie($row['click_id'], $my_secret_key, $cookie_name, $secure_cookie);
            // 200 + delayed redirect: Set-Cookie is more reliably stored than on an immediate 302 to t.co
            redirectToBufferAfterCookie($buffer_url);
            break;
        case 'BUFFER':
            finalizeAndRedirectToOffer($row, $api_key, $cookie_name, $secure_cookie);
            break;
        case 'FINALIZED':
            redirectToOfferFromRow($row);
            break;
        default:
            echo 'Unknown state: ' . htmlspecialchars((string) $row['state'], ENT_QUOTES, 'UTF-8');
            exit;
    }
}

// Return visit: no query params — use signed cookie (e.g. after buffer stripped params)
$resume_click_id = parseResumeCookie($_COOKIE[$cookie_name] ?? '', $my_secret_key);
if ($resume_click_id === null) {
    die('Invalid');
}

$data = getApiData("{$data_base}?API_KEY={$api_key}&click_id=" . rawurlencode($resume_click_id));
$row = normalizeClickRow($data['clickRedirectionData'] ?? null);
if ($row === null) {
    die('Click not found');
}

switch ($row['state']) {
    case 'BUFFER':
        finalizeAndRedirectToOffer($row, $api_key, $cookie_name, $secure_cookie);
        break;
    case 'FINALIZED':
        redirectToOfferFromRow($row);
        break;
    case 'INIT':
        die('Invalid session');
    default:
        echo 'Unknown state: ' . htmlspecialchars((string) $row['state'], ENT_QUOTES, 'UTF-8');
        exit;
}

/**
 * @param mixed $raw
 * @return array<string, mixed>|null
 */
function normalizeClickRow($raw): ?array
{
    if (!is_array($raw) || empty($raw['click_id'])) {
        return null;
    }

    return $raw;
}

function buildOfferUrl(array $row): string
{
    $offer_url = $row['affiliate_link'];
    $offer_url .= (strpos($offer_url, '?') !== false ? '&' : '?');
    $offer_url .= $row['affiliate_token'] . '=' . $row['click_id'];

    return $offer_url;
}

function redirectToOfferFromRow(array $row): void
{
    RedirectTo(buildOfferUrl($row));
}

function finalizeAndRedirectToOffer(array $row, string $api_key, string $cookie_name, bool $secure_cookie): void
{
    updateClickRedirectionData($row['click_id'], 'FINALIZED', $api_key);
    clearResumeCookie($cookie_name, $secure_cookie);
    redirectToOfferFromRow($row);
}

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $fwd = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($fwd === 'https') {
        return true;
    }
    $fwdSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
    if (strtolower((string) $fwdSsl) === 'on') {
        return true;
    }

    return false;
}

/**
 * Cookie domain so www and apex both receive the same cookie (host-only cookies break on host switch).
 *
 * @return array<string, mixed>
 */
function resumeCookieOptions(int $expires, bool $secure): array
{
    $opts = [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host !== '' && !filter_var($host, FILTER_VALIDATE_IP)) {
        $root = preg_replace('/^www\./i', '', $host);
        if (strtolower($root) !== 'localhost') {
            $opts['domain'] = $root;
        }
    }

    return $opts;
}

function setResumeCookie(string $click_id, string $secret, string $cookie_name, bool $secure_cookie): void
{
    $value = buildResumeCookieValue($click_id, $secret);
    setcookie($cookie_name, $value, resumeCookieOptions(time() + 86400, $secure_cookie));
}

function clearResumeCookie(string $cookie_name, bool $secure_cookie): void
{
    setcookie($cookie_name, '', resumeCookieOptions(time() - 3600, $secure_cookie));
}

/**
 * Send Set-Cookie then a full HTML response so the browser commits the cookie before leaving for the buffer (shorteners).
 */
function redirectToBufferAfterCookie(string $buffer_url): void
{
    header('Content-Type: text/html; charset=UTF-8');
    $href = htmlspecialchars($buffer_url, ENT_QUOTES, 'UTF-8');
    $jsUrl = json_encode($buffer_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $href . '">';
    echo '<title>Redirecting…</title></head><body>';
    echo '<p>Redirecting… <a href="' . $href . '">Continue</a></p>';
    echo '<script>location.replace(' . $jsUrl . ');</script>';
    echo '</body></html>';
    exit;
}

function buildResumeCookieValue(string $click_id, string $secret): string
{
    $sig = hash_hmac('sha256', $click_id, $secret);

    return base64_encode($click_id . '|' . $sig);
}

function parseResumeCookie(string $raw, string $secret): ?string
{
    if ($raw === '') {
        return null;
    }
    $decoded = base64_decode($raw, true);
    if ($decoded === false) {
        return null;
    }
    $parts = explode('|', $decoded, 2);
    if (count($parts) !== 2) {
        return null;
    }
    [$click_id, $sig] = $parts;
    if ($click_id === '') {
        return null;
    }
    $expected = hash_hmac('sha256', $click_id, $secret);
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    return $click_id;
}

function updateClickRedirectionData($click_id, $new_state, $api_key)
{
    $url = 'https://debugging.wisemindvibe.com/api/clicks/update.php?API_KEY=' . rawurlencode($api_key);
    $payload = [
        'click_id' => $click_id,
        'state' => $new_state,
    ];
    postJsonApi($url, $payload);
}

function postJsonApi($url, array $payload)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        die('cURL error: ' . $err);
    }
    if ($http_code >= 400) {
        die('HTTP error: ' . $http_code . "\nResponse: " . $response);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
        die('Request failed: ' . json_last_error_msg() . "\nResponse: " . $response);
    }

    return $data;
}

function RedirectTo($url)
{
    header('Location: ' . $url);
    exit;
}

function getApiData($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        die('cURL error: ' . $err);
    }
    if ($http_code >= 400) {
        die('HTTP error: ' . $http_code . "\nResponse: " . $response);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
        die('Request failed: ' . json_last_error_msg() . "\nResponse: " . $response);
    }

    return $data;
}
