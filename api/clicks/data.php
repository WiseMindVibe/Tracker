<?php
require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');

$apiKey = (string) ($_GET['API_KEY'] ?? '');
if (!hash_equals('1234567890', $apiKey)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
    ]);
    exit;
}

$clickId = trim((string) ($_GET['click_id'] ?? ''));
if ($clickId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Click ID is required',
    ]);
    exit;
}

$db = db();
$stmt = $db->prepare("SELECT * FROM click_redirections WHERE click_id = :click_id");
$stmt->execute(['click_id' => $clickId]);
$clickRedirectionData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($clickRedirectionData === false) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Click not found',
        'clickRedirectionData' => null,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'clickRedirectionData' => $clickRedirectionData,
]);
exit;
