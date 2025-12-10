<?php
require_once __DIR__ . '/../src/bootstrap.php';

// 1. Extract required parameters (this depends on affiliate network)
$clickId = isset($_GET['click_id']) ? $_GET['click_id'] : null; //SUB_ID
$payout  = isset($_GET['payout']) ? $_GET['payout'] : null; //COMMISION
$status  = strtolower(isset($_GET['status']) ? $_GET['status'] : null);
$event_id = isset($_GET['event_id']) ? $_GET['event_id'] : null;

// 2. Basic validation
if(!$clickId || !$payout || !$status) {
    logPostback($clickId, "Missing parameters", "click_id: $clickId, payout: $payout, status: $status");
    exit("Error: Missing parameters.<br>click_id: $clickId<br> payout: $payout<br>status: $status");
}

// 3. Prevent duplicate conversions
$stmt = db()->prepare("SELECT status FROM clicks WHERE click_id = :click_id LIMIT 1");
$stmt->execute([':click_id' => $clickId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

// 4. Check if click exists
if (!$existing) {
    logPostback($clickId, "Click not found", "Click $clickId not found");
    exit("Error: Click Not Found");
}
else if ($existing && in_array(strtolower($existing['status']), ['confirmed', 'paid', 'rejected'])) {
    logPostback($clickId, "Duplicate conversion", "Click_id: $clickId already has status: " . $existing['status']);
}

// Check for allowed status values
$allowedStatuses = ["open", "confirmed", "paid", "rejected"];
if (!in_array($status, $allowedStatuses)) {
    logPostback($clickId, "Invalid status", "Received status: $status");
    exit("Error: Invalid status");
}
// Validate payout
if (!floatval($payout) || $payout < 0) {
    logPostback($clickId, "Invalid payout", "Received payout: $payout");
    exit("Error: Invalid payout");
}


// 5. Update click row
$stmt = db()->prepare("
    UPDATE clicks 
    SET status = :status, payout = :payout, event_id = :event_id, updated_at = NOW()
    WHERE click_id = :click_id
");
$stmt->execute([
    ':status'   => $status,
    ':payout'   => $payout,
    ':click_id' => $clickId,
    ':event_id' => $event_id
]);

// 6. Respond to affiliate server
echo "OK";
