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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON',
    ]);
    exit;
}

$clickId = trim((string) ($data['click_id'] ?? ''));
$state = trim((string) ($data['state'] ?? ''));

if ($clickId === '' || $state === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'click_id and state are required',
    ]);
    exit;
}

$allowedStates = ['INIT', 'BUFFER', 'FINALIZED'];
if (!in_array($state, $allowedStates, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid state value',
        'allowed_states' => $allowedStates,
    ]);
    exit;
}

$db = db();
$currentStmt = $db->prepare("SELECT state FROM click_redirections WHERE click_id = :click_id");
$currentStmt->execute([':click_id' => $clickId]);
$currentState = $currentStmt->fetchColumn();

if ($currentState === false) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Click not found',
    ]);
    exit;
}

$currentState = (string) $currentState;
if ($currentState === $state) {
    echo json_encode([
        'success' => true,
        'updated' => 0,
        'no_change' => true,
        'state' => $currentState,
    ]);
    exit;
}

$allowedTransitions = [
    'INIT' => ['BUFFER'],
    'BUFFER' => ['FINALIZED'],
    'FINALIZED' => [],
];

if (!isset($allowedTransitions[$currentState]) || !in_array($state, $allowedTransitions[$currentState], true)) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid state transition',
        'current_state' => $currentState,
        'requested_state' => $state,
    ]);
    exit;
}

$stmt = $db->prepare("UPDATE click_redirections SET state = :state WHERE click_id = :click_id");
$stmt->execute([':state' => $state, ':click_id' => $clickId]);

echo json_encode([
    'success' => true,
    'updated' => (int) $stmt->rowCount(),
    'no_change' => ((int) $stmt->rowCount()) === 0,
    'previous_state' => $currentState,
    'state' => $state,
]);
exit;
