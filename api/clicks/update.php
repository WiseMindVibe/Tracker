<?php
require_once __DIR__ . '/../../src/bootstrap.php';

$apiKey = $_GET['API_KEY'] ?? null;
if($apiKey !== '1234567890' ){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// read raw JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$db = db();
$stmt = $db->prepare("UPDATE click_redirections SET state = :state WHERE click_id = :click_id");
$stmt->execute([':state' => $data['state'], ':click_id' => $data['click_id']]);

echo json_encode([
    'success' => true,
    'updated' => $stmt->rowCount()
]);
exit;