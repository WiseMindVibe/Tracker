<?php

/**
 * Read-only analytics for a single offer (clicks, conversions, campaigns).
 */
class ModelInspect
{
    private const CONV_STATUSES = "('open','confirmed','paid','rejected','delayed')";

    /** @return list<array{id: int, name: string}> */
    public static function listOffersForSelect(): array
    {
        $stmt = db()->query('SELECT id, name FROM offers ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{first_click: ?string, last_click: ?string, total_clicks: int}
     */
    public static function fetchOfferClickBounds(int $offerId): array
    {
        $stmt = db()->prepare('
            SELECT
                MIN(c.created_at) AS first_click,
                MAX(c.created_at) AS last_click,
                COUNT(*) AS total_clicks
            FROM clicks c
            WHERE c.offer_id = :oid
        ');
        $stmt->execute([':oid' => $offerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'first_click' => isset($row['first_click']) && $row['first_click'] !== null
                ? (string) $row['first_click']
                : null,
            'last_click' => isset($row['last_click']) && $row['last_click'] !== null
                ? (string) $row['last_click']
                : null,
            'total_clicks' => (int) ($row['total_clicks'] ?? 0),
        ];
    }

    /**
     * Dashboard-style aggregates scoped to one offer and date range (inclusive days).
     *
     * @return array<string, float|int|null>
     */
    public static function fetchOfferStats(int $offerId, string $start, string $end): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_clicks,
                SUM(CASE WHEN cv.status IN " . self::CONV_STATUSES . " THEN 1 ELSE 0 END) AS total_conversions,
                SUM(CASE WHEN cv.status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN cv.status = 'open' THEN cv.payout ELSE 0 END) AS open_sum,
                SUM(CASE WHEN cv.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN cv.status = 'confirmed' THEN cv.payout ELSE 0 END) AS confirmed_sum,
                SUM(CASE WHEN cv.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN cv.status = 'paid' THEN cv.payout ELSE 0 END) AS paid_sum,
                SUM(CASE WHEN cv.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                SUM(CASE WHEN cv.status = 'rejected' THEN cv.payout ELSE 0 END) AS rejected_sum,
                SUM(CASE WHEN cv.status = 'delayed' THEN 1 ELSE 0 END) AS delayed_count,
                SUM(CASE WHEN cv.status = 'delayed' THEN cv.payout ELSE 0 END) AS delayed_sum,
                COALESCE(SUM(c.cost), 0) AS cost,
                COALESCE(SUM(cv.payout), 0) AS revenue_all_statuses
            FROM clicks c
            LEFT JOIN conversions cv ON cv.click_internal_id = c.id
            WHERE c.offer_id = :oid
              AND c.created_at >= :start
              AND c.created_at <= :end
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':oid' => $offerId,
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cost = (float) ($row['cost'] ?? 0);
        $revenue = (float) ($row['revenue_all_statuses'] ?? 0);
        $profit = $revenue - $cost;
        $roi = $cost > 0 ? ($profit / $cost) * 100.0 : null;
        $totalClicks = (int) ($row['total_clicks'] ?? 0);
        $totalConversions = (int) ($row['total_conversions'] ?? 0);
        $rejectedCount = (int) ($row['rejected_count'] ?? 0);
        $rejectionRate = $totalConversions > 0 ? ($rejectedCount / $totalConversions) * 100.0 : null;
        $cr = $totalClicks > 0 ? ($totalConversions / $totalClicks) * 100.0 : null;
        $epc = $totalClicks > 0 ? $revenue / $totalClicks : null;

        return [
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'open_count' => (int) ($row['open_count'] ?? 0),
            'open_sum' => (float) ($row['open_sum'] ?? 0),
            'confirmed_count' => (int) ($row['confirmed_count'] ?? 0),
            'confirmed_sum' => (float) ($row['confirmed_sum'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'paid_sum' => (float) ($row['paid_sum'] ?? 0),
            'rejected_count' => $rejectedCount,
            'rejected_sum' => (float) ($row['rejected_sum'] ?? 0),
            'delayed_count' => (int) ($row['delayed_count'] ?? 0),
            'delayed_sum' => (float) ($row['delayed_sum'] ?? 0),
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'roi' => $roi,
            'rejection_rate' => $rejectionRate,
            'cr' => $cr,
            'epc' => $epc,
        ];
    }

    /**
     * Clicks grouped by campaign (clicks.campaign_id may be UUID or numeric legacy id).
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchCampaignClickBreakdown(
        int $offerId,
        string $start7,
        string $start30,
        string $endToday
    ): array {
        $sql = "
            SELECT
                c.campaign_id AS click_campaign_ref,
                MAX(cam.id) AS campaign_id,
                MAX(cam.uuid) AS campaign_uuid,
                MAX(cam.name) AS campaign_name,
                MAX(cam.tester) AS tester,
                MAX(ts.name) AS traffic_name,
                COUNT(*) AS clicks_lifetime,
                SUM(CASE
                    WHEN c.created_at >= :start7 AND c.created_at <= :end7
                    THEN 1 ELSE 0 END) AS clicks_7d,
                SUM(CASE
                    WHEN c.created_at >= :start30 AND c.created_at <= :end30
                    THEN 1 ELSE 0 END) AS clicks_30d,
                MIN(c.created_at) AS first_click,
                MAX(c.created_at) AS last_click
            FROM clicks c
            LEFT JOIN campaigns cam
                ON cam.uuid = c.campaign_id OR cam.id = c.campaign_id
            LEFT JOIN traffic_sources ts ON ts.id = cam.traffic_source_id
            WHERE c.offer_id = :oid
            GROUP BY c.campaign_id
            HAVING COUNT(*) > 0
            ORDER BY clicks_lifetime DESC, campaign_name ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':oid' => $offerId,
            ':start7' => $start7 . ' 00:00:00',
            ':end7' => $endToday . ' 23:59:59',
            ':start30' => $start30 . ' 00:00:00',
            ':end30' => $endToday . ' 23:59:59',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Offer rows in campaign_offers (cap / current_views). campaign_id is campaigns.id (INT).
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchCampaignOfferAssignments(int $offerId): array
    {
        $sql = "
            SELECT
                cam.id AS campaign_id,
                cam.uuid AS campaign_uuid,
                cam.name AS campaign_name,
                cam.tester AS tester,
                ts.name AS traffic_name,
                COALESCE(co.cap, 0) AS cap,
                COALESCE(co.current_views, 0) AS current_views,
                CASE WHEN COALESCE(co.cap, 0) > 0 THEN 1 ELSE 0 END AS assignment_active,
                CASE
                    WHEN COALESCE(co.cap, 0) > 0 AND COALESCE(co.current_views, 0) < COALESCE(co.cap, 0)
                    THEN 1 ELSE 0 END AS has_remaining_cap
            FROM campaign_offers co
            INNER JOIN campaigns cam ON cam.id = co.campaign_id
            LEFT JOIN traffic_sources ts ON ts.id = cam.traffic_source_id
            WHERE co.offer_id = :oid
            ORDER BY cam.name ASC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([':oid' => $offerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, string> campaign_id => space-separated external ids
     */
    public static function fetchExternalIdsByCampaignIds(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter(array_map('intval', $campaignIds))));
        if ($campaignIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
        $sql = "
            SELECT campaign_id,
                   GROUP_CONCAT(DISTINCT external_campaign_id ORDER BY external_campaign_id SEPARATOR ' ') AS ext
            FROM campaign_external_ids
            WHERE campaign_id IN ($placeholders)
            GROUP BY campaign_id
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute($campaignIds);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int) ($row['campaign_id'] ?? 0);
            if ($cid > 0) {
                $out[$cid] = trim((string) ($row['ext'] ?? ''));
            }
        }

        return $out;
    }

    /**
     * @return list<array{country: string, clicks: int}>
     */
    public static function fetchTopCountries(int $offerId, string $start, string $end, int $limit = 10): array
    {
        $limit = max(1, min(25, $limit));
        $sql = "
            SELECT COALESCE(NULLIF(TRIM(c.country), ''), '(unknown)') AS country, COUNT(*) AS clicks
            FROM clicks c
            WHERE c.offer_id = :oid
              AND c.created_at >= :start
              AND c.created_at <= :end
            GROUP BY COALESCE(NULLIF(TRIM(c.country), ''), '(unknown)')
            ORDER BY clicks DESC
            LIMIT $limit
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':oid' => $offerId,
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['clicks'] = (int) ($r['clicks'] ?? 0);
        }
        unset($r);

        return $rows;
    }

    /**
     * Merge click breakdown with assignment cap state and external ids.
     *
     * @param list<array<string, mixed>> $clickRows
     * @param list<array<string, mixed>> $assignments
     * @param array<int, string> $externalByCampaignId
     * @return list<array<string, mixed>>
     */
    public static function mergeCampaignRows(
        array $clickRows,
        array $assignments,
        array $externalByCampaignId
    ): array {
        $byCid = [];
        $byUuid = [];
        foreach ($assignments as $a) {
            $cid = (int) ($a['campaign_id'] ?? 0);
            if ($cid > 0) {
                $byCid[$cid] = $a;
            }
            $uuid = trim((string) ($a['campaign_uuid'] ?? ''));
            if ($uuid !== '') {
                $byUuid[strtolower($uuid)] = $a;
            }
        }

        $merged = [];
        foreach ($clickRows as $row) {
            $cid = (int) ($row['campaign_id'] ?? 0);
            $ref = trim((string) ($row['click_campaign_ref'] ?? ''));
            $assign = null;
            if ($cid > 0 && isset($byCid[$cid])) {
                $assign = $byCid[$cid];
            } elseif ($ref !== '' && isset($byUuid[strtolower($ref)])) {
                $assign = $byUuid[strtolower($ref)];
                if ($cid < 1 && $assign !== null) {
                    $cid = (int) ($assign['campaign_id'] ?? 0);
                    $row['campaign_id'] = $cid > 0 ? $cid : $row['campaign_id'];
                    $row['campaign_uuid'] = $row['campaign_uuid'] ?? ($assign['campaign_uuid'] ?? null);
                    $row['campaign_name'] = $row['campaign_name'] ?? ($assign['campaign_name'] ?? '');
                }
            }
            $extCid = (int) ($row['campaign_id'] ?? 0);
            if ($extCid < 1 && $assign !== null) {
                $extCid = (int) ($assign['campaign_id'] ?? 0);
            }
            $merged[] = array_merge($row, [
                'cap' => $assign !== null ? (int) ($assign['cap'] ?? 0) : null,
                'current_views' => $assign !== null ? (int) ($assign['current_views'] ?? 0) : null,
                'assignment_active' => $assign !== null ? (int) ($assign['assignment_active'] ?? 0) : 0,
                'has_remaining_cap' => $assign !== null ? (int) ($assign['has_remaining_cap'] ?? 0) : 0,
                'external_ids' => $extCid > 0 ? ($externalByCampaignId[$extCid] ?? '') : '',
            ]);
        }

        return $merged;
    }

    /**
     * Assignments that have no resolved campaign join (data issue) — still surface raw keys.
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchOrphanAssignments(int $offerId): array
    {
        $sql = "
            SELECT co.campaign_id AS raw_campaign_ref, co.cap, co.current_views
            FROM campaign_offers co
            LEFT JOIN campaigns cam ON cam.id = co.campaign_id
            WHERE co.offer_id = :oid AND cam.id IS NULL
        ";
        $stmt = db()->prepare($sql);
        $stmt->execute([':oid' => $offerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
