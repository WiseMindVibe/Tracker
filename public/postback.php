<?php

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/services/telegram.php';

/**
 * Accepts legacy params (click_id, payout, status) and YieldKit-style S2S macros
 * (SUB_ID, COMMISSION, STATE, EVENT_TYPE, COMMISSION_ID, EVENT_ID, SALES_AMOUNT, …).
 */
function normalizeConversionStatus(string $raw): ?string
{
    $t = strtoupper(trim($raw));
    static $map = [
        'OPEN' => 'open',
        'CONFIRMED' => 'confirmed',
        'REJECTED' => 'rejected',
        'DELAYED' => 'delayed',
        'PAID' => 'paid',
    ];
    if (isset($map[$t])) {
        return $map[$t];
    }
    $l = strtolower(trim($raw));
    if (in_array($l, ['open', 'confirmed', 'paid', 'rejected', 'delayed'], true)) {
        return $l;
    }

    return null;
}

function parseOptionalDatetime(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $ts = strtotime($raw);

    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

$publicClickId = $_GET['SUB_ID'] ?? $_GET['click_id'] ?? null;
$publicClickId = $publicClickId !== null ? trim((string) $publicClickId) : null;

$commissionRaw = $_GET['COMMISSION'] ?? $_GET['payout'] ?? null;
$stateRaw = $_GET['STATE'] ?? $_GET['status'] ?? null;

$eventType = strtoupper(trim((string) ($_GET['EVENT_TYPE'] ?? '')));
if ($eventType !== 'NEW' && $eventType !== 'UPDATE' && $eventType !== '') {
    tracker_postback_log('invalid_event_type', $publicClickId, 'EVENT_TYPE', $eventType);
    exit('ERROR');
}

$commissionIdParam = isset($_GET['COMMISSION_ID']) ? trim((string) $_GET['COMMISSION_ID']) : '';
$eventIdParam = $_GET['EVENT_ID'] ?? $_GET['event_id'] ?? null;
$eventIdParam = $eventIdParam !== null ? trim((string) $eventIdParam) : '';

$salesAmountRaw = $_GET['SALES_AMOUNT'] ?? null;
$salesDateRaw = $_GET['SALES_DATE'] ?? null;
$modifiedDateRaw = $_GET['MODIFIED_DATE'] ?? null;

if ($publicClickId === null || $publicClickId === '') {
    tracker_postback_log('missing_click_id', null, 'SUB_ID / click_id required');
    exit('ERROR');
}

if ($commissionRaw === null || $commissionRaw === '' || !is_numeric($commissionRaw)) {
    tracker_postback_log('invalid_commission', $publicClickId, 'COMMISSION / payout must be numeric', $commissionRaw);
    exit('ERROR');
}

$commissionEur = (float) $commissionRaw;

if ($stateRaw === null || trim((string) $stateRaw) === '') {
    tracker_postback_log('missing_state', $publicClickId, 'STATE / status required');
    exit('ERROR');
}

$status = normalizeConversionStatus((string) $stateRaw);
if ($status === null) {
    tracker_postback_log('invalid_status', $publicClickId, 'Unknown STATE/status', $stateRaw);
    exit('ERROR');
}

$salesAmountEur = null;
if ($salesAmountRaw !== null && $salesAmountRaw !== '' && is_numeric($salesAmountRaw)) {
    $salesAmountEur = (float) $salesAmountRaw;
}

$salesDate = parseOptionalDatetime($salesDateRaw !== null ? (string) $salesDateRaw : null);
$modifiedDate = parseOptionalDatetime($modifiedDateRaw !== null ? (string) $modifiedDateRaw : null);

if ($commissionIdParam !== '') {
    $dedupeKey = substr($commissionIdParam, 0, 192);
} elseif ($eventIdParam !== '') {
    $dedupeKey = 'evt:' . substr($eventIdParam, 0, 180);
} else {
    $dedupeKey = 'legacy';
}

$db = db();

$db->beginTransaction();

try {
    $stmt = $db->prepare('
        SELECT c.id, c.offer_id, c.created_at, o.affiliate_program_id
        FROM clicks c
        INNER JOIN offers o ON o.id = c.offer_id
        WHERE c.click_id = :click_id
        FOR UPDATE
    ');
    $stmt->execute([':click_id' => $publicClickId]);
    $click = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $db->rollBack();
    tracker_postback_log('db_error', $publicClickId, $e->getMessage());
    exit('ERROR');
}

if (!$click) {
    $db->rollBack();
    tracker_postback_log('click_not_found', $publicClickId, 'No click row for sub id');
    exit('ERROR');
}

$internalId = (int) $click['id'];
$affiliateAccountId = (int) $click['affiliate_program_id'];

try {
    $stmt = $db->prepare('
        INSERT INTO commission_events (
            click_internal_id, dedupe_key, commission_id, external_event_id, event_type,
            commission_eur, sales_amount_eur, state, sales_date, modified_date
        ) VALUES (
            :cid, :dedupe, :comm_id, :evt_id, :evtype,
            :commission_eur, :sales_amt, :state, :sales_dt, :mod_dt
        )
        ON DUPLICATE KEY UPDATE
            commission_eur = VALUES(commission_eur),
            sales_amount_eur = VALUES(sales_amount_eur),
            state = VALUES(state),
            event_type = VALUES(event_type),
            external_event_id = VALUES(external_event_id),
            sales_date = VALUES(sales_date),
            modified_date = VALUES(modified_date),
            updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([
        ':cid' => $internalId,
        ':dedupe' => $dedupeKey,
        ':comm_id' => $commissionIdParam !== '' ? $commissionIdParam : null,
        ':evt_id' => $eventIdParam !== '' ? $eventIdParam : null,
        ':evtype' => $eventType !== '' ? $eventType : null,
        ':commission_eur' => $commissionEur,
        ':sales_amt' => $salesAmountEur,
        ':state' => $status,
        ':sales_dt' => $salesDate,
        ':mod_dt' => $modifiedDate,
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    tracker_postback_log('commission_event_failed', $publicClickId, $e->getMessage());
    exit('ERROR');
}

$sumStmt = $db->prepare('SELECT COALESCE(SUM(commission_eur), 0) AS s FROM commission_events WHERE click_internal_id = :id');
$sumStmt->execute([':id' => $internalId]);
$payoutSum = (float) ($sumStmt->fetchColumn() ?: 0);

$latestStmt = $db->prepare('
    SELECT state, external_event_id
    FROM commission_events
    WHERE click_internal_id = :id
    ORDER BY COALESCE(modified_date, updated_at) DESC, id DESC
    LIMIT 1
');
$latestStmt->execute([':id' => $internalId]);
$latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: ['state' => $status, 'external_event_id' => ($eventIdParam !== '' ? $eventIdParam : null)];
$rollupStatus = (string) ($latest['state'] ?? $status);
$rollupEventId = $latest['external_event_id'] ?? ($eventIdParam !== '' ? $eventIdParam : null);

try {
    $stmt = $db->prepare('
        INSERT INTO conversions (click_internal_id, status, payout, external_event_id, created_at, updated_at)
        VALUES (:click_internal_id, :status, :payout, :external_event_id, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            payout = VALUES(payout),
            external_event_id = VALUES(external_event_id),
            updated_at = NOW()
    ');
    $stmt->execute([
        ':click_internal_id' => $internalId,
        ':status' => $rollupStatus,
        ':payout' => $payoutSum,
        ':external_event_id' => $rollupEventId,
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    tracker_postback_log('conversion_rollup_failed', $publicClickId, $e->getMessage());
    exit('ERROR');
}

$stmt = $db->prepare('
    SELECT
        c.id AS internal_click_id,
        c.click_id AS public_click_id,
        o.name AS offer_name,
        aa.affiliate_program AS affiliate_name,
        cv.status,
        cv.payout,
        c.created_at,
        cv.updated_at
    FROM clicks c
    JOIN conversions cv ON cv.click_internal_id = c.id
    JOIN offers o ON o.id = c.offer_id
    JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
    WHERE c.id = :click_internal_id
');
$stmt->execute([':click_internal_id' => $internalId]);
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$notification) {
    $db->rollBack();
    tracker_postback_log('notification_context_missing', $publicClickId, 'Join failed after conversion write');
    exit('ERROR');
}

$eventTypeDb = $eventType !== '' ? $eventType : null;
$commissionIdDb = $commissionIdParam !== '' ? $commissionIdParam : null;
$externalEvtDb = $eventIdParam !== '' ? $eventIdParam : null;

try {
    $stmt = $db->prepare('
        INSERT INTO notifications (
            click_id,
            offer_id,
            affiliate_id,
            status,
            payout,
            click_created_at,
            click_updated_at,
            event_type,
            commission_id,
            external_event_id,
            currency,
            sales_amount
        )
        VALUES (
            :click_pk,
            (SELECT offer_id FROM clicks WHERE id = :cid1),
            (SELECT affiliate_program_id FROM offers WHERE id = (SELECT offer_id FROM clicks WHERE id = :cid2)),
            :status,
            :payout,
            :created_at,
            :updated_at,
            :event_type,
            :commission_id,
            :external_event_id,
            :currency,
            :sales_amount
        )
    ');
    $stmt->execute([
        ':click_pk' => $internalId,
        ':cid1' => $internalId,
        ':cid2' => $internalId,
        ':status' => $status,
        ':payout' => $commissionEur,
        ':created_at' => $notification['created_at'],
        ':updated_at' => $notification['updated_at'],
        ':event_type' => $eventTypeDb,
        ':commission_id' => $commissionIdDb,
        ':external_event_id' => $externalEvtDb,
        ':currency' => 'EUR',
        ':sales_amount' => $salesAmountEur,
    ]);
} catch (Throwable $e) {
    $db->rollBack();
    tracker_postback_log('notification_insert_failed', $publicClickId, $e->getMessage());
    exit('ERROR');
}

$db->commit();

$evtLabel = $eventTypeDb ?? '—';
$commLabel = $commissionIdDb ?? '—';
$message = "
<b>💰 Conversion</b> ({$evtLabel})

<b>Offer:</b> {$notification['offer_name']}
<b>Affiliate:</b> {$notification['affiliate_name']}
<b>State:</b> {$status}
<b>Commission (EUR):</b> {$commissionEur}

<b>Rollup payout:</b> {$payoutSum} EUR
<b>Commission id:</b> {$commLabel}
";

sendTelegramMessage($message);

tracker_postback_log('ok', $publicClickId, 'processed', $dedupeKey);

echo 'OK';
