<?php
// src/functions/reporting.php
// Pure functions: parseDateInput(), getReportData()

/**
 * Convert browser datetime-local value (YYYY-MM-DDTHH:MM) into MySQL DATETIME string
 * Returns a string like "2025-12-03 14:30:00"
 */
function parseDateInput(?string $raw, string $default): string {
    if (!$raw) return $default;
    // Accept a few formats: "YYYY-MM-DDTHH:MM", "YYYY-MM-DD HH:MM:SS", etc.
    $raw = str_replace('T', ' ', $raw);
    $ts = strtotime($raw);
    if ($ts === false) return $default;
    return date('Y-m-d H:i:s', $ts);
}

/**
 * Fetch aggregated report rows grouped by offer -> campaign -> os -> browser.
 * Returns flat rows (PDO FETCH_ASSOC).
 */
function getReportRows(string $startMysql, string $endMysql): array {
    $db = db();

    // Use JOIN because we only want offers that received clicks in range.
    $sql = "
    SELECT 
        o.id AS offer_id,
        o.name AS offer_name,
        c.campaign_id,
        COALESCE(NULLIF(TRIM(c.OS), ''), 'Unknown') AS os,
        COALESCE(NULLIF(TRIM(c.browser), ''), 'Unknown') AS browser,

        COUNT(c.id) AS clicks,
        SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) AS conversions,
        IFNULL(SUM(c.payout), 0) AS revenue,
        IFNULL(SUM(c.cost), 0) AS cost

    FROM clicks c
    JOIN offers o ON o.id = c.offer_id
    WHERE c.created_at BETWEEN ? AND ?
    GROUP BY o.id, c.campaign_id, os, browser
    ORDER BY o.id ASC, c.campaign_id ASC, os ASC, browser ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$startMysql, $endMysql]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Build nested report array from flat rows:
 * [
 *  offerId => [
 *    'offer_name' => ..,
 *    'clicks' => ..,
 *    'revenue' => ..,
 *    'cost' => ..,
 *    'conversions' => ..,
 *    'campaigns' => [
 *      campaignId => [
 *        'clicks'=>.., 'revenue'=>.., 'cost'=>.., 'conversions'=>..,
 *        'os' => [ 'Android' => [ 'clicks'=>.., 'browsers' => [ 'Chrome'=>[...] ] ] ]
 *      ]
 *    ]
 *  ]
 * ]
 */
function buildReport(array $rows): array {
    $report = [];

    foreach ($rows as $r) {
        $offerId    = (int)$r['offer_id'];
        $campaignId = $r['campaign_id'] === null ? '0' : (string)$r['campaign_id'];
        $osName     = $r['os'] ?? 'Unknown';
        $browser    = $r['browser'] ?? 'Unknown';

        // Offer level
        if (!isset($report[$offerId])) {
            $report[$offerId] = [
                'offer_name'  => $r['offer_name'],
                'clicks'      => 0,
                'revenue'     => 0.0,
                'cost'        => 0.0,
                'conversions' => 0,
                'campaigns'   => [],
            ];
        }

        $report[$offerId]['clicks']      += (int)$r['clicks'];
        $report[$offerId]['revenue']     += (float)$r['revenue'];
        $report[$offerId]['cost']        += (float)$r['cost'];
        $report[$offerId]['conversions'] += (int)$r['conversions'];

        // Campaign level
        if (!isset($report[$offerId]['campaigns'][$campaignId])) {
            $report[$offerId]['campaigns'][$campaignId] = [
                'clicks'      => 0,
                'revenue'     => 0.0,
                'cost'        => 0.0,
                'conversions' => 0,
                'os'          => [],
            ];
        }
        $report[$offerId]['campaigns'][$campaignId]['clicks']      += (int)$r['clicks'];
        $report[$offerId]['campaigns'][$campaignId]['revenue']     += (float)$r['revenue'];
        $report[$offerId]['campaigns'][$campaignId]['cost']        += (float)$r['cost'];
        $report[$offerId]['campaigns'][$campaignId]['conversions'] += (int)$r['conversions'];

        // OS level
        if (!isset($report[$offerId]['campaigns'][$campaignId]['os'][$osName])) {
            $report[$offerId]['campaigns'][$campaignId]['os'][$osName] = [
                'clicks'      => 0,
                'revenue'     => 0.0,
                'cost'        => 0.0,
                'conversions' => 0,
                'browsers'    => [],
            ];
        }
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['clicks']      += (int)$r['clicks'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['revenue']     += (float)$r['revenue'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['cost']        += (float)$r['cost'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['conversions'] += (int)$r['conversions'];

        // Browser level
        if (!isset($report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser])) {
            $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser] = [
                'clicks'      => 0,
                'revenue'     => 0.0,
                'cost'        => 0.0,
                'conversions' => 0,
            ];
        }
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser]['clicks']      += (int)$r['clicks'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser]['revenue']     += (float)$r['revenue'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser]['cost']        += (float)$r['cost'];
        $report[$offerId]['campaigns'][$campaignId]['os'][$osName]['browsers'][$browser]['conversions'] += (int)$r['conversions'];
    }

    return $report;
}
