<?php

// Tracker → Blog
if (isset($_GET['ref'])) {

    $ref = $_GET['ref'];

    // Decode Base64
    $json = base64_decode($ref, true);

    if ($json === false) {
        exit('Invalid ref');
    }

    // Decode JSON
    $payload = json_decode($json, true);

    if (!is_array($payload)) {
        exit('Invalid JSON');
    }

    // Save payload in cookie
    setcookie(
        'ref',
        $json,
        [
            'expires' => time() + 60,
            'path' => '/',
            'secure' => false, // true when using HTTPS
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );

    // Blog → Buffer
    header('Location: ' . $payload['buffer_url']);
    exit;
}


// Buffer → Blog → Affiliate
if (isset($_COOKIE['ref'])) {

    // Get payload from cookie
    $payload = json_decode($_COOKIE['ref'], true);

    if (!is_array($payload)) {
        exit('Invalid cookie');
    }

    // Build affiliate URL
    $separator = str_contains($payload['affiliate_link'], '?')
        ? '&'
        : '?';

    $utms = "utm_source=" . $payload["domain"] . "&utm_medium=affiliate&utm_campaign=ctr_campaign";

    $affiliateUrl =
        $payload['affiliate_link']
        . $separator
        . $payload['affiliate_token']
        . '='
        . $payload['click_id']
        . "&" . $utms;

    // Browser performs the final navigation
    header('Content-Type: text/html');
    header('Referrer-Policy: origin');

    echo '<!DOCTYPE html>
<html>
<head>
    <meta name="referrer" content="origin">
</head>
<body>
<script>
    window.location.href = ' . json_encode($affiliateUrl) . ';
</script>
</body>
</html>';

    exit;
}


// Normal visitor
echo 'Normal website visitor';
