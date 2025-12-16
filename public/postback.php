<?php
require_once __DIR__ . '/../src/bootstrap.php';

$clickId  = $_GET['click_id'] ?? null; // SUB_ID
$payout   = $_GET['payout'] ?? null;
$status   = strtolower($_GET['status'] ?? null);
$event_id = $_GET['event_id'] ?? null;

if (!$clickId || !$payout || !$status) {
    logPostback($clickId, "Missing parameters");
    exit("ERROR");
}

$allowedStatuses = ["open", "confirmed", "paid", "rejected"];
if (!in_array($status, $allowedStatuses)) {
    logPostback($clickId, "Invalid status", $status);
    exit("ERROR");
}

if (!is_numeric($payout) || $payout < 0) {
    logPostback($clickId, "Invalid payout", $payout);
    exit("ERROR");
}

$db = db();
$db->beginTransaction();

/**
 * 1. Fetch click row (LOCKED)
 */
$stmt = $db->prepare("
    SELECT id, status
    FROM clicks
    WHERE click_id = :click_id
    FOR UPDATE
");
$stmt->execute([':click_id' => $clickId]);
$click = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$click) {
    $db->rollBack();
    logPostback($clickId, "Click not found");
    exit("ERROR");
}

/**
 * 2. Prevent duplicate conversion
 */
if (in_array($click['status'], ['confirmed', 'paid', 'rejected'])) {
    $db->rollBack();
    logPostback($clickId, "Duplicate conversion", $click['status']);
    exit("OK");
}

/**
 * 3. Update click
 */
$stmt = $db->prepare("
    UPDATE clicks
    SET status = :status,
        payout = :payout,
        updated_at = NOW()
    WHERE id = :id
");
$stmt->execute([
    ':status'   => $status,
    ':payout'   => $payout,
    ':id'       => $click['id']
]);

/**
 * 4. Insert notification
 */
$stmt = $db->prepare("
    INSERT INTO notifications (
        click_id,
        offer_id,
        affiliate_id,
        status,
        payout,
        click_created_at,
        click_updated_at
    )
    SELECT
        c.id,
        c.offer_id,
        o.affiliate_program_id,
        c.status,
        c.payout,
        c.created_at,
        c.updated_at

    FROM clicks c
    JOIN offers o ON o.id = c.offer_id
    WHERE c.id = :click_internal_id
");
$stmt->execute([
    ':click_internal_id' => $click['id']
]);

$db->commit();

echo "OK";
