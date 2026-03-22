<?php

/**
 * Debug / audit logging for redirect and postback flows.
 * Expects tables shaped by migration 009_Revamp_debug_logging_tables.php.
 */

function tracker_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function tracker_raw_query_string(): string
{
    $q = $_SERVER['QUERY_STRING'] ?? '';
    if (strlen($q) > 8000) {
        return substr($q, 0, 8000);
    }

    return $q;
}

/**
 * @param array<string, mixed>|null $context Optional JSON-serializable context
 */
function tracker_redirect_log(string $status, string $reason = '', string $campaignId = '', ?array $context = null, string $level = 'info'): void
{
    try {
        $ctx = $context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
        $stmt = db()->prepare('
            INSERT INTO redirect_logs (status, reason, campaign_id, raw_query, ip, context, level, created_at)
            VALUES (:status, :reason, :campaign_id, :raw_query, INET6_ATON(:ip), :context, :level, NOW())
        ');
        $stmt->execute([
            ':status' => substr($status, 0, 80),
            ':reason' => $reason === '' ? null : substr($reason, 0, 512),
            ':campaign_id' => substr($campaignId, 0, 64),
            ':raw_query' => tracker_raw_query_string(),
            ':ip' => tracker_client_ip(),
            ':context' => $ctx,
            ':level' => substr($level, 0, 16),
        ]);
    } catch (Throwable $e) {
        // never break redirect/postback
    }
}

/**
 * @param mixed $detail Extra detail stored in reason (truncated) or logged alone
 */
function tracker_postback_log(string $status, ?string $clickId, string $reason = '', $detail = null): void
{
    try {
        $fullReason = $reason;
        if ($detail !== null && $detail !== '') {
            $fullReason = trim($reason . ' | ' . (is_scalar($detail) ? (string) $detail : json_encode($detail)));
        }
        $stmt = db()->prepare('
            INSERT INTO postback_logs (status, reason, click_id, raw_query, ip, created_at)
            VALUES (:status, :reason, :click_id, :raw_query, INET6_ATON(:ip), NOW())
        ');
        $stmt->execute([
            ':status' => substr($status, 0, 80),
            ':reason' => $fullReason === '' ? null : substr($fullReason, 0, 512),
            ':click_id' => $clickId !== null && $clickId !== '' ? substr((string) $clickId, 0, 64) : null,
            ':raw_query' => tracker_raw_query_string(),
            ':ip' => tracker_client_ip(),
        ]);
    } catch (Throwable $e) {
    }
}

/** @deprecated use tracker_redirect_log */
function log_redirect(string $status, string $reason = '', string $campaignId = ''): void
{
    tracker_redirect_log($status, $reason, $campaignId);
}

/** Backwards-compatible alias for postback.php */
function logPostback(?string $clickId, string $reason, $detail = null): void
{
    $status = 'error';
    $lr = strtolower($reason);
    if (strpos($lr, 'missing') !== false) {
        $status = 'missing_parameters';
    } elseif (strpos($lr, 'invalid') !== false) {
        $status = 'invalid_input';
    } elseif (strpos($lr, 'not found') !== false) {
        $status = 'click_not_found';
    } elseif (strpos($lr, 'duplicate') !== false) {
        $status = 'duplicate_ignored';
    }
    tracker_postback_log($status, $clickId, $reason, $detail);
}
