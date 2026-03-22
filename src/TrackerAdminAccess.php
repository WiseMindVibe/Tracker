<?php

/**
 * Optional gate for the web UI (index.php only). Public click/postback endpoints
 * under public/ are intentionally not protected here.
 *
 * Enable by setting TRACKER_ACCESS_TOKEN in .env (long random string).
 * Optionally set TRACKER_ALLOWED_IPS to a comma-separated list of allowed IPs (IPv4/IPv6).
 *
 * First visit: open index.php?tracker_token=YOUR_TOKEN (from an allowed IP if configured).
 * A session cookie is set so normal navigation does not need the token in the URL.
 */
final class TrackerAdminAccess
{
    public static function enforceOrExit(): void
    {
        $token = getenv('TRACKER_ACCESS_TOKEN');
        if ($token === false || trim($token) === '') {
            return;
        }
        $token = trim($token);

        $allowedIps = self::parseAllowedIps(getenv('TRACKER_ALLOWED_IPS'));
        if ($allowedIps !== []) {
            $ip = self::clientIp();
            if (!self::ipInList($ip, $allowedIps)) {
                self::deny(403, 'Forbidden');
            }
        }

        // In query strings '+' is decoded as a space; tokens from base64-like generators often contain '+'.
        $getToken = isset($_GET['tracker_token']) && is_string($_GET['tracker_token']) && $_GET['tracker_token'] !== ''
            ? str_replace(' ', '+', (string) $_GET['tracker_token'])
            : null;
        $cookieToken = isset($_COOKIE['tracker_access']) && is_string($_COOKIE['tracker_access']) && $_COOKIE['tracker_access'] !== ''
            ? (string) $_COOKIE['tracker_access']
            : null;
        $headerToken = isset($_SERVER['HTTP_X_TRACKER_TOKEN']) && is_string($_SERVER['HTTP_X_TRACKER_TOKEN'])
            ? trim((string) $_SERVER['HTTP_X_TRACKER_TOKEN'])
            : '';

        // Valid ?tracker_token= logs in, sets cookie, strips token from URL (avoids leaking token in Referer).
        if ($getToken !== null && hash_equals($token, $getToken)) {
            self::persistCookie($token);
            self::redirectStripTokenFromUrl();
        }
        if ($cookieToken !== null && hash_equals($token, $cookieToken)) {
            return;
        }
        if ($headerToken !== '' && hash_equals($token, $headerToken)) {
            return;
        }
        self::deny(401, 'Unauthorized');
    }

    private static function persistCookie(string $token): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('tracker_access', $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function redirectStripTokenFromUrl(): void
    {
        $params = $_GET;
        unset($params['tracker_token']);
        $path = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $q = http_build_query($params);
        $loc = $path . ($q !== '' ? '?' . $q : '');
        header('Location: ' . $loc, true, 302);
        exit;
    }

    /**
     * @param string|false $raw
     * @return list<string>
     */
    private static function parseAllowedIps($raw): array
    {
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $ip = trim($part);
            if ($ip !== '') {
                $out[] = $ip;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $allowed
     */
    private static function ipInList(string $client, array $allowed): bool
    {
        if ($client === '') {
            return false;
        }
        foreach ($allowed as $a) {
            if (strcasecmp($client, $a) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function clientIp(): string
    {
        $mode = getenv('TRACKER_IP_SOURCE');
        $mode = $mode !== false ? strtoupper(trim($mode)) : '';
        if ($mode === 'X_FORWARDED_FOR') {
            $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (is_string($xff) && $xff !== '') {
                $parts = array_map('trim', explode(',', $xff));
                if (isset($parts[0]) && $parts[0] !== '') {
                    return $parts[0];
                }
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function deny(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
}
