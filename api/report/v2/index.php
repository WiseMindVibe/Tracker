<?php

require_once __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/TrackerAdminAccess.php';
require_once __DIR__ . '/../../../src/controllers/ControllerReportV2.php';

header('Content-Type: application/json; charset=utf-8');

TrackerAdminAccess::enforceOrExit();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Unable to read request body'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (trim($raw) === '') {
    $payload = [];
} else {
    try {
        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!is_array($payload)) {
    http_response_code(422);
    echo json_encode(['error' => 'Request body must be a JSON object'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $controller = new ControllerReportV2();
    $response = $controller->handle($payload);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to build report'], JSON_UNESCAPED_UNICODE);
}
