<?php

/**
 * Resolve a tracked click token (clicks.click_id) to the final affiliate URL and redirect.
 * Used after redirect.php sends users to the buffer site with ?tc=...
 *
 * WordPress (mu-plugin): redirect to this script when tc is present, e.g.
 *   $tc = isset($_GET['tc']) ? sanitize_text_field(wp_unslash($_GET['tc'])) : '';
 *   if ($tc !== '') {
 *       wp_redirect('https://track.wisemindvibe.com/public/go.php?tc=' . rawurlencode($tc));
 *       exit;
 *   }
 */

require_once __DIR__ . '/../src/bootstrap.php';

$token = isset($_GET['tc']) ? trim((string) $_GET['tc']) : '';
if ($token === '' && isset($_GET['t'])) {
    $token = trim((string) $_GET['t']);
}

if ($token === '' || !preg_match('/^cid[a-zA-Z0-9._-]{10,200}$/', $token)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or missing click token.';
    exit;
}

try {
    $stmt = db()->prepare('
        SELECT o.affiliate_link, o.affiliate_program_id, c.click_id
        FROM clicks c
        INNER JOIN offers o ON o.id = c.offer_id
        WHERE c.click_id = :cid
        LIMIT 1
    ');
    $stmt->execute([':cid' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server error.';
    exit;
}

if ($row === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unknown click.';
    exit;
}

$programName = affiliateProgramSlugFromAccountId((int) $row['affiliate_program_id']);
$target = buildCustomAffiliateUrl((string) $row['affiliate_link'], $programName, (string) $row['click_id']);

header('Location: ' . $target, true, 302);
exit;
