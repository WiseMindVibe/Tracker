<?php

const API_RESPONSE_SEPARATOR = "\nResponse: ";

loadEnvFile(__DIR__ . '/../.env');

$my_secret_key = 'SECRET_KEY'; // CHANGE LATER IN .env
$cookie_name = 'click_resume';
$buffer_url = trim((string) (getenv('TRACKER_DEFAULT_BUFFER_URL') ?: 'https://t.co/KUibROt82H'));
$api_key = '1234567890'; // CHANGE LATER
$api_base = normalizeBaseUrl(
    (string) (getenv('REDIRECTION_API_BASE_URL') ?: 'https://track.wisemindvibe.com'),
    isHttpsRequest()
);

if ($api_base === '') {
    failRequest('Configuration error', 500);
}
if (!isValidUrl($buffer_url)) {
    failRequest('Invalid buffer URL configuration', 500);
}

$data_api_url = $api_base . '/api/clicks/data.php';
$update_api_url = $api_base . '/api/clicks/update.php';
$secure_cookie = isHttpsRequest();

redirectionDebug('start', [
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
    'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    'has_signed_params' => hasSignedQueryParams(),
    'cookie_present' => isset($_COOKIE[$cookie_name]),
    'secure_cookie' => $secure_cookie,
]);

if (hasSignedQueryParams()) {
    $click_id = urldecode((string) $_GET['click_id']);
    $hash = urldecode((string) $_GET['hash']);

    $expected_hash = hash_hmac('sha256', $click_id, $my_secret_key);
    if (!hash_equals($expected_hash, $hash)) {
        failRequest('Invalid', 400);
    }

    $row = fetchClickRowOrFail($data_api_url, $api_key, $click_id);
    switch ($row['state']) {
        case 'INIT':
            updateClickRedirectionData($row['click_id'], 'BUFFER', $api_key, $update_api_url);
            setResumeCookie($row['click_id'], $my_secret_key, $cookie_name, $secure_cookie);
            // 200 + delayed redirect: Set-Cookie is more reliably stored than immediate 302 to shortener URLs.
            redirectToBufferAfterCookie($buffer_url);
            break;
        case 'BUFFER':
            finalizeAndRedirectToOffer($row, $api_key, $update_api_url, $cookie_name, $secure_cookie);
            break;
        case 'FINALIZED':
            clearResumeCookie($cookie_name, $secure_cookie);
            redirectToOfferFromRow($row);
            break;
        default:
            failRequest('Unknown state: ' . htmlspecialchars((string) $row['state'], ENT_QUOTES, 'UTF-8'), 409);
    }
}

// Return visit: no query params (e.g. after third-party buffer strips params).
$resume_click_id = parseResumeCookie((string) ($_COOKIE[$cookie_name] ?? ''), $my_secret_key);
if ($resume_click_id === null) {
    redirectionDebug('resume_cookie_missing_or_invalid', [
        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        'cookie_present' => isset($_COOKIE[$cookie_name]),
    ]);
    failRequest('Invalid', 400);
}

$row = fetchClickRowOrFail($data_api_url, $api_key, $resume_click_id);
switch ($row['state']) {
    case 'BUFFER':
        finalizeAndRedirectToOffer($row, $api_key, $update_api_url, $cookie_name, $secure_cookie);
        break;
    case 'FINALIZED':
        clearResumeCookie($cookie_name, $secure_cookie);
        redirectToOfferFromRow($row);
        break;
    case 'INIT':
        failRequest('Invalid session', 409);
        break;
    default:
        failRequest('Unknown state: ' . htmlspecialchars((string) $row['state'], ENT_QUOTES, 'UTF-8'), 409);
}

function hasSignedQueryParams(): bool
{
    return isset($_GET['click_id'], $_GET['hash']) && $_GET['click_id'] !== '' && $_GET['hash'] !== '';
}

function loadEnvFile(string $env_path): void
{
    if (!is_file($env_path)) {
        return;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }

        putenv($name . '=' . trim($value));
    }
}

function normalizeBaseUrl(string $raw, bool $prefer_https): string
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }

    if (!preg_match('/^https?:\/\//i', $value)) {
        $value = ($prefer_https ? 'https://' : 'http://') . $value;
    }

    $parts = parse_url($value);
    if (!is_array($parts) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ($prefer_https ? 'https' : 'http')));
    if ($scheme !== 'http' && $scheme !== 'https') {
        $scheme = $prefer_https ? 'https' : 'http';
    }

    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = rtrim((string) ($parts['path'] ?? ''), '/');

    return $scheme . '://' . $host . $port . $path;
}

function isValidUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $scheme === 'http' || $scheme === 'https';
}

function fetchClickRowOrFail(string $data_api_url, string $api_key, string $click_id): array
{
    $data = getApiData("{$data_api_url}?API_KEY=" . rawurlencode($api_key) . '&click_id=' . rawurlencode($click_id));
    $row = normalizeClickRow($data['clickRedirectionData'] ?? null);
    if ($row === null) {
        failRequest('Click not found', 404);
    }

    return $row;
}

/**
 * @param mixed $raw
 * @return array<string, mixed>|null
 */
function normalizeClickRow($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }

    $click_id = trim((string) ($raw['click_id'] ?? ''));
    $state = trim((string) ($raw['state'] ?? ''));
    if ($click_id === '' || $state === '') {
        return null;
    }

    $raw['click_id'] = $click_id;
    $raw['state'] = $state;
    return $raw;
}

function buildOfferUrl(array $row): string
{
    $affiliate_link = trim((string) ($row['affiliate_link'] ?? ''));
    $affiliate_token = trim((string) ($row['affiliate_token'] ?? ''));
    $click_id = trim((string) ($row['click_id'] ?? ''));

    if ($affiliate_link === '' || $affiliate_token === '' || $click_id === '') {
        failRequest('Invalid click data', 500);
    }

    if (!filter_var($affiliate_link, FILTER_VALIDATE_URL)) {
        failRequest('Invalid offer URL', 500);
    }

    $scheme = strtolower((string) parse_url($affiliate_link, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        failRequest('Invalid offer URL', 500);
    }

    $separator = (strpos($affiliate_link, '?') !== false) ? '&' : '?';
    $query = http_build_query([$affiliate_token => $click_id], '', '&', PHP_QUERY_RFC3986);
    return $affiliate_link . $separator . $query;
}

function redirectToOfferFromRow(array $row): void
{
    redirectTo(buildOfferUrl($row));
}

function finalizeAndRedirectToOffer(
    array $row,
    string $api_key,
    string $update_api_url,
    string $cookie_name,
    bool $secure_cookie
): void {
    redirectionDebug('finalize_click', [
        'click_id' => $row['click_id'] ?? '',
        'state' => $row['state'] ?? '',
    ]);
    updateClickRedirectionData($row['click_id'], 'FINALIZED', $api_key, $update_api_url);
    clearResumeCookie($cookie_name, $secure_cookie);
    redirectToOfferFromRow($row);
}

function isHttpsRequest(): bool
{
    $https_flag = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $forwarded_proto_https = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $forwarded_ssl_on = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';

    return $https_flag || $forwarded_proto_https || $forwarded_ssl_on;
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

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = (string) preg_replace('/:\d+$/', '', $host);
    $cookie_domain = resolveCookieDomain($host);
    if ($cookie_domain !== null) {
        $opts['domain'] = $cookie_domain;
    }

    redirectionDebug('cookie_options', [
        'host' => $host,
        'domain' => $opts['domain'] ?? '(host-only)',
        'secure' => $secure,
    ]);

    return $opts;
}

function resolveCookieDomain(string $host): ?string
{
    $host = strtolower(trim($host));
    $domain = null;

    if ($host !== '' && $host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP)) {
        $host = ltrim($host, '.');
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false) {
            $parts = explode('.', $host);
            $count = count($parts);
            if ($count <= 2) {
                $domain = $host;
            } else {
                $tail = $parts[$count - 2] . '.' . $parts[$count - 1];
                $common_second_level_tlds = [
                    'co.uk',
                    'org.uk',
                    'ac.uk',
                    'gov.uk',
                    'com.au',
                    'net.au',
                    'org.au',
                    'co.nz',
                    'com.br',
                ];

                $domain = in_array($tail, $common_second_level_tlds, true) && $count >= 3
                    ? $parts[$count - 3] . '.' . $tail
                    : $tail;
            }
        }
    }

    return $domain;
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
 * Send Set-Cookie then a full HTML response so the browser commits the cookie before leaving for the buffer.
 */
function redirectToBufferAfterCookie(string $buffer_url): void
{
    header('Content-Type: text/html; charset=UTF-8');
    $href = htmlspecialchars($buffer_url, ENT_QUOTES, 'UTF-8');
    $jsUrl = json_encode($buffer_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="0;url=' . $href . '">';
    echo '<title>Redirecting...</title></head><body>';
    echo '<p>Redirecting... <a href="' . $href . '">Continue</a></p>';
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
    $click_id = null;

    if ($raw !== '') {
        $decoded = base64_decode($raw, true);
        if ($decoded !== false) {
            $parts = explode('|', $decoded, 2);
            if (count($parts) === 2) {
                [$candidate_click_id, $sig] = $parts;
                if ($candidate_click_id !== '') {
                    $expected = hash_hmac('sha256', $candidate_click_id, $secret);
                    if (hash_equals($expected, $sig)) {
                        $click_id = $candidate_click_id;
                    }
                }
            }
        }
    }

    return $click_id;
}

function updateClickRedirectionData(string $click_id, string $new_state, string $api_key, string $update_api_url): void
{
    $url = $update_api_url . '?API_KEY=' . rawurlencode($api_key);
    $payload = [
        'click_id' => $click_id,
        'state' => $new_state,
    ];

    postJsonApi($url, $payload);
}

/**
 * @return array<string, mixed>
 */
function postJsonApi(string $url, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        failRequest('cURL error: ' . $err, 502);
    }
    if ($http_code >= 400) {
        failRequest('HTTP error: ' . $http_code . API_RESPONSE_SEPARATOR . $response, 502);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        failRequest('Request failed: ' . json_last_error_msg() . API_RESPONSE_SEPARATOR . $response, 502);
    }

    return $data;
}

/**
 * @return array<string, mixed>
 */
function getApiData(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        failRequest('cURL error: ' . $err, 502);
    }
    if ($http_code >= 400) {
        failRequest('HTTP error: ' . $http_code . API_RESPONSE_SEPARATOR . $response, 502);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        failRequest('Request failed: ' . json_last_error_msg() . API_RESPONSE_SEPARATOR . $response, 502);
    }

    return $data;
}

function redirectTo(string $url): void
{
    if (preg_match('/[\r\n]/', $url) === 1) {
        failRequest('Invalid redirect URL', 400);
    }

    header('Location: ' . $url, true, 302);
    exit;
}

function failRequest(string $message, int $status_code = 400): void
{
    redirectionDebug('fail', [
        'status_code' => $status_code,
        'message' => $message,
        'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
    ]);

    if (!headers_sent()) {
        http_response_code($status_code);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo $message;
    exit;
}

function redirectionDebug(string $event, array $context = []): void
{
    $raw = getenv('REDIRECTION_DEBUG');
    if ($raw === false || trim((string) $raw) === '') {
        return;
    }
    if (!filter_var(trim((string) $raw), FILTER_VALIDATE_BOOLEAN)) {
        return;
    }

    error_log('[REDIRECTION_DEBUG] ' . $event . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES));
}
