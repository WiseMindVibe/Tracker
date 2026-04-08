<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$apiKey = $_GET['API_KEY'] ?? null;
if($apiKey !== '1234567890' ){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clickId = $_GET['click_id'] ?? null;
if(!$clickId){
    http_response_code(400);
    echo json_encode(['error' => 'Click ID is required']);
    exit;
}

$db = db();
$stmt = $db->prepare("SELECT * FROM click_redirections WHERE click_id = :click_id");
$stmt->execute(['click_id' => $clickId]);
$clickRedirectionData = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'clickRedirectionData' => $clickRedirectionData
]);
exit;