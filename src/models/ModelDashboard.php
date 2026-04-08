<?php

require_once __DIR__ . '/../services/conversion_metrics.php';

class ModelDashboard
{
    /** @see migration 004.1_create_conversions_table */
    private const STATUS_OPEN = 1;
    private const STATUS_CONFIRMED = 2;
    private const STATUS_REJECTED = 3;
    private const STATUS_PAID = 4;

    /**
     * @return array{start: string, end: string}
     */
    private static function dateBounds(string $start, string $end): array
    {
        return [
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ];
    }

    /**
     * Resolve preset or custom range to inclusive Y-m-d bounds (server timezone).
     *
     * @return array{start: string, end: string, preset: string}
     */
    public static function resolveDateRange(string $preset, ?string $customStart = null, ?string $customEnd = null): array
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $today = new DateTime('today', $tz);
        $preset = strtolower(trim($preset));

        if ($preset === 'custom' && $customStart !== null && $customStart !== '' && $customEnd !== null && $customEnd !== '') {
            $s = DateTime::createFromFormat('Y-m-d', $customStart, $tz);
            $e = DateTime::createFromFormat('Y-m-d', $customEnd, $tz);
            if ($s instanceof DateTime && $e instanceof DateTime && $s <= $e) {
                return [
                    'start' => $s->format('Y-m-d'),
                    'end' => $e->format('Y-m-d'),
                    'preset' => 'custom',
                ];
            }
        }

        switch ($preset) {
            case 'yesterday':
                $d = (clone $today)->modify('-1 day');
                return [
                    'start' => $d->format('Y-m-d'),
                    'end' => $d->format('Y-m-d'),
                    'preset' => 'yesterday',
                ];
            case 'last7':
                $start = (clone $today)->modify('-6 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'last7',
                ];
            case 'last30':
                $start = (clone $today)->modify('-29 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'last30',
                ];
            case 'previous_month':
                $start = (clone $today)->modify('first day of previous month');
                $end = (clone $today)->modify('last day of previous month');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'preset' => 'previous_month',
                ];
            case 'last_year':
                $start = (clone $today)->modify('-365 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'last_year',
                ];
            case 'this_week':
                $dow = (int) $today->format('N');
                $start = (clone $today)->modify('-' . ($dow - 1) . ' days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'this_week',
                ];
            case 'this_month':
                $start = (clone $today)->modify('first day of this month');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'this_month',
                ];
            case 'this_year':
                $start = (clone $today)->modify('first day of January this year');
                $end = (clone $today)->modify('last day of December this year');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d'),
                    'preset' => 'this_year',
                ];
            case 'all_time':
                return [
                    'start' => '2000-01-01',
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'all_time',
                ];
            case 'today':
            default:
                return [
                    'start' => $today->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                    'preset' => 'today',
                ];
        }
    }

    /**
     * @return array<string, float|int|null>
     */
    public static function fetchAggregateStats(string $start, string $end): array
    {
        $bounds = self::dateBounds($start, $end);
        $pdo = db();

        $o = self::STATUS_OPEN;
        $cf = self::STATUS_CONFIRMED;
        $rj = self::STATUS_REJECTED;
        $pd = self::STATUS_PAID;

        // Single round-trip: two one-row derived tables (avoids duplicate click scan vs two statements).
        $sql = "
            SELECT
                clic.total_clicks,
                clic.cost,
                COALESCE(conv.total_conversions, 0) AS total_conversions,
                COALESCE(conv.open_count, 0) AS open_count,
                COALESCE(conv.open_sum, 0) AS open_sum,
                COALESCE(conv.confirmed_count, 0) AS confirmed_count,
                COALESCE(conv.confirmed_sum, 0) AS confirmed_sum,
                COALESCE(conv.paid_count, 0) AS paid_count,
                COALESCE(conv.paid_sum, 0) AS paid_sum,
                COALESCE(conv.rejected_count, 0) AS rejected_count,
                COALESCE(conv.rejected_sum, 0) AS rejected_sum,
                COALESCE(conv.revenue_all_statuses, 0) AS revenue_all_statuses,
                COALESCE(conv.revenue_for_roi, 0) AS revenue_for_roi
            FROM (
                SELECT
                    COUNT(*) AS total_clicks,
                    COALESCE(SUM(c.cost), 0) AS cost
                FROM clicks c
                WHERE c.created_at >= :start AND c.created_at <= :end
            ) clic
            CROSS JOIN (
                SELECT
                    SUM(CASE WHEN cv.status IN (1,2,3,4) THEN 1 ELSE 0 END) AS total_conversions,
                    SUM(CASE WHEN cv.status = {$o} THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN cv.status = {$o} THEN cv.revenue ELSE 0 END) AS open_sum,
                    SUM(CASE WHEN cv.status = {$cf} THEN 1 ELSE 0 END) AS confirmed_count,
                    SUM(CASE WHEN cv.status = {$cf} THEN cv.revenue ELSE 0 END) AS confirmed_sum,
                    SUM(CASE WHEN cv.status = {$pd} THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN cv.status = {$pd} THEN cv.revenue ELSE 0 END) AS paid_sum,
                    SUM(CASE WHEN cv.status = {$rj} THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN cv.status = {$rj} THEN cv.revenue ELSE 0 END) AS rejected_sum,
                    COALESCE(SUM(CASE WHEN cv.status IN (1,2,3,4) THEN cv.revenue ELSE 0 END), 0) AS revenue_all_statuses,
                    COALESCE(SUM(CASE WHEN cv.status != {$rj} THEN cv.revenue ELSE 0 END), 0) AS revenue_for_roi
                FROM conversions cv
                INNER JOIN clicks c ON c.id = cv.click_id
                WHERE c.created_at >= :start AND c.created_at <= :end
            ) conv
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bounds);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cost = (float) ($row['cost'] ?? 0);
        $revenueTotal = (float) ($row['revenue_all_statuses'] ?? 0);
        $revenueForRoi = (float) ($row['revenue_for_roi'] ?? 0);
        $profit = $revenueForRoi - $cost;
        $roi = ConversionMetrics::roiPercent($cost, $revenueForRoi);
        $totalClicks = (int) ($row['total_clicks'] ?? 0);
        $totalConversions = (int) ($row['total_conversions'] ?? 0);
        $openCount = (int) ($row['open_count'] ?? 0);
        $confirmedCount = (int) ($row['confirmed_count'] ?? 0);
        $paidCount = (int) ($row['paid_count'] ?? 0);
        $rejectedCount = (int) ($row['rejected_count'] ?? 0);
        $cr = ConversionMetrics::conversionRate($totalClicks, $totalConversions);
        $rejectionRate = ConversionMetrics::rejectionRate($openCount, $confirmedCount, $paidCount, $rejectedCount);

        return [
            'total_clicks' => $totalClicks,
            'total_conversions' => $totalConversions,
            'open_count' => $openCount,
            'open_sum' => (float) ($row['open_sum'] ?? 0),
            'confirmed_count' => $confirmedCount,
            'confirmed_sum' => (float) ($row['confirmed_sum'] ?? 0),
            'paid_count' => $paidCount,
            'paid_sum' => (float) ($row['paid_sum'] ?? 0),
            'rejected_count' => $rejectedCount,
            'rejected_sum' => (float) ($row['rejected_sum'] ?? 0),
            'cost' => $cost,
            'revenue' => $revenueTotal,
            'revenue_for_roi' => $revenueForRoi,
            'profit' => $profit,
            'roi' => $roi,
            'cr' => $cr,
            'rejection_rate' => $rejectionRate,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchAffiliateBreakdown(string $start, string $end): array
    {
        $bounds = self::dateBounds($start, $end);
        $pdo = db();

        $sqlClicks = "
            SELECT
                aa.id AS affiliate_id,
                COALESCE(aa.affiliate_program, 'Unassigned') AS affiliate_name,
                COUNT(c.id) AS clicks,
                COALESCE(SUM(c.cost), 0) AS cost
            FROM clicks c
            LEFT JOIN offers o ON o.id = c.offer_id
            LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
            WHERE c.created_at >= :start AND c.created_at <= :end
            GROUP BY aa.id, aa.affiliate_program
        ";
        $stmt = $pdo->prepare($sqlClicks);
        $stmt->execute($bounds);
        $clickRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlConv = "
            SELECT
                aa.id AS affiliate_id,
                COALESCE(aa.affiliate_program, 'Unassigned') AS affiliate_name,
                COUNT(cv.id) AS conversions,
                SUM(CASE WHEN cv.status = 1 THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN cv.status = 2 THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN cv.status = 4 THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN cv.status = 3 THEN 1 ELSE 0 END) AS rejected_count,
                COALESCE(SUM(CASE WHEN cv.status IN (1,2,3,4) THEN cv.revenue ELSE 0 END), 0) AS revenue_total,
                COALESCE(SUM(CASE WHEN cv.status != 3 THEN cv.revenue ELSE 0 END), 0) AS revenue_for_roi
            FROM conversions cv
            INNER JOIN clicks c ON c.id = cv.click_id
            LEFT JOIN offers o ON o.id = c.offer_id
            LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
            WHERE c.created_at >= :start AND c.created_at <= :end
              AND cv.status IN (1,2,3,4)
            GROUP BY aa.id, aa.affiliate_program
        ";
        $stmt = $pdo->prepare($sqlConv);
        $stmt->execute($bounds);
        $convRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $merged = [];

        $affiliateKey = static function (array $r): string {
            $idRaw = $r['affiliate_id'] ?? null;
            if ($idRaw !== null && $idRaw !== '') {
                return 'i' . (int) $idRaw;
            }

            return 'u';
        };

        foreach ($clickRows as $r) {
            $key = $affiliateKey($r);
            $idRaw = $r['affiliate_id'] ?? null;
            $merged[$key] = [
                'affiliate_id' => ($idRaw !== null && $idRaw !== '') ? (int) $idRaw : null,
                'affiliate_name' => (string) ($r['affiliate_name'] ?? 'Unassigned'),
                'clicks' => (int) ($r['clicks'] ?? 0),
                'conversions' => 0,
                'open_count' => 0,
                'confirmed_count' => 0,
                'paid_count' => 0,
                'rejected_count' => 0,
                'cost' => (float) ($r['cost'] ?? 0),
                'revenue' => 0.0,
                'revenue_for_roi' => 0.0,
            ];
        }

        foreach ($convRows as $r) {
            $key = $affiliateKey($r);
            $idRaw = $r['affiliate_id'] ?? null;
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'affiliate_id' => ($idRaw !== null && $idRaw !== '') ? (int) $idRaw : null,
                    'affiliate_name' => (string) ($r['affiliate_name'] ?? 'Unassigned'),
                    'clicks' => 0,
                    'conversions' => 0,
                    'open_count' => 0,
                    'confirmed_count' => 0,
                    'paid_count' => 0,
                    'rejected_count' => 0,
                    'cost' => 0.0,
                    'revenue' => 0.0,
                    'revenue_for_roi' => 0.0,
                ];
            }
            $merged[$key]['conversions'] = (int) ($r['conversions'] ?? 0);
            $merged[$key]['open_count'] = (int) ($r['open_count'] ?? 0);
            $merged[$key]['confirmed_count'] = (int) ($r['confirmed_count'] ?? 0);
            $merged[$key]['paid_count'] = (int) ($r['paid_count'] ?? 0);
            $merged[$key]['rejected_count'] = (int) ($r['rejected_count'] ?? 0);
            $merged[$key]['revenue'] = (float) ($r['revenue_total'] ?? 0);
            $merged[$key]['revenue_for_roi'] = (float) ($r['revenue_for_roi'] ?? 0);
        }

        $rows = [];
        foreach ($merged as $row) {
            $clicks = (int) $row['clicks'];
            $conversions = (int) $row['conversions'];
            $cost = (float) $row['cost'];
            $revenueTotal = (float) $row['revenue'];
            $revenueForRoi = (float) $row['revenue_for_roi'];
            if ($clicks === 0 && $conversions === 0 && $cost <= 0.0 && $revenueTotal <= 0.0) {
                continue;
            }
            $row['profit'] = $revenueForRoi - $cost;
            $row['cr'] = ConversionMetrics::conversionRate($clicks, $conversions) ?? 0.0;
            $row['roi'] = ConversionMetrics::roiPercent($cost, $revenueForRoi);
            $row['rejection_rate'] = ConversionMetrics::rejectionRate(
                (int) $row['open_count'],
                (int) $row['confirmed_count'],
                (int) $row['paid_count'],
                (int) $row['rejected_count']
            );
            unset($row['open_count'], $row['confirmed_count'], $row['paid_count'], $row['rejected_count'], $row['revenue_for_roi']);
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            $pa = (float) $a['profit'];
            $pb = (float) $b['profit'];
            if ($pb <=> $pa) {
                return $pb <=> $pa;
            }

            return (int) $b['clicks'] <=> (int) $a['clicks'];
        });

        return $rows;
    }

    /**
     * Rolling last 7 days including today; independent of main dashboard range.
     *
     * @return array{by_roi: list<array>, by_cr: list<array>, by_profit: list<array>}
     */
    public static function fetchTopOffersLast7Days(int $limit = 5): array
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $end = new DateTime('today', $tz);
        $start = (clone $end)->modify('-6 days');
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');
        $bounds = self::dateBounds($startStr, $endStr);
        $pdo = db();

        $sqlClicks = "
            SELECT
                o.id AS offer_id,
                o.name AS offer_name,
                COUNT(c.id) AS clicks,
                COALESCE(SUM(c.cost), 0) AS cost
            FROM clicks c
            INNER JOIN offers o ON o.id = c.offer_id
            WHERE c.created_at >= :start AND c.created_at <= :end
            GROUP BY o.id, o.name
            HAVING COUNT(c.id) > 0
        ";
        $stmt = $pdo->prepare($sqlClicks);
        $stmt->execute($bounds);
        $clickRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sqlConv = "
            SELECT
                o.id AS offer_id,
                o.name AS offer_name,
                COUNT(cv.id) AS conversions,
                SUM(CASE WHEN cv.status = 1 THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN cv.status = 2 THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN cv.status = 4 THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN cv.status = 3 THEN 1 ELSE 0 END) AS rejected_count,
                COALESCE(SUM(CASE WHEN cv.status IN (1,2,3,4) THEN cv.revenue ELSE 0 END), 0) AS revenue_total,
                COALESCE(SUM(CASE WHEN cv.status != 3 THEN cv.revenue ELSE 0 END), 0) AS revenue_for_roi
            FROM conversions cv
            INNER JOIN clicks c ON c.id = cv.click_id
            INNER JOIN offers o ON o.id = c.offer_id
            WHERE c.created_at >= :start AND c.created_at <= :end
              AND cv.status IN (1,2,3,4)
            GROUP BY o.id, o.name
        ";
        $stmt = $pdo->prepare($sqlConv);
        $stmt->execute($bounds);
        $convRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byOffer = [];
        foreach ($clickRows as $r) {
            $oid = (int) $r['offer_id'];
            $byOffer[$oid] = [
                'offer_id' => $oid,
                'offer_name' => (string) $r['offer_name'],
                'clicks' => (int) $r['clicks'],
                'conversions' => 0,
                'open_count' => 0,
                'confirmed_count' => 0,
                'paid_count' => 0,
                'rejected_count' => 0,
                'cost' => (float) ($r['cost'] ?? 0),
                'revenue_total' => 0.0,
                'revenue_for_roi' => 0.0,
            ];
        }
        foreach ($convRows as $r) {
            $oid = (int) $r['offer_id'];
            if (!isset($byOffer[$oid])) {
                $byOffer[$oid] = [
                    'offer_id' => $oid,
                    'offer_name' => (string) $r['offer_name'],
                    'clicks' => 0,
                    'conversions' => 0,
                    'open_count' => 0,
                    'confirmed_count' => 0,
                    'paid_count' => 0,
                    'rejected_count' => 0,
                    'cost' => 0.0,
                    'revenue_total' => 0.0,
                    'revenue_for_roi' => 0.0,
                ];
            }
            $byOffer[$oid]['conversions'] = (int) ($r['conversions'] ?? 0);
            $byOffer[$oid]['open_count'] = (int) ($r['open_count'] ?? 0);
            $byOffer[$oid]['confirmed_count'] = (int) ($r['confirmed_count'] ?? 0);
            $byOffer[$oid]['paid_count'] = (int) ($r['paid_count'] ?? 0);
            $byOffer[$oid]['rejected_count'] = (int) ($r['rejected_count'] ?? 0);
            $byOffer[$oid]['revenue_total'] = (float) ($r['revenue_total'] ?? 0);
            $byOffer[$oid]['revenue_for_roi'] = (float) ($r['revenue_for_roi'] ?? 0);
        }

        $enriched = [];
        foreach ($byOffer as $o) {
            $cost = (float) $o['cost'];
            $revenueForRoi = (float) $o['revenue_for_roi'];
            $clicks = (int) $o['clicks'];
            $conversions = (int) $o['conversions'];
            $profit = $revenueForRoi - $cost;
            $cr = ConversionMetrics::conversionRate($clicks, $conversions) ?? 0.0;
            $roi = ConversionMetrics::roiPercent($cost, $revenueForRoi);
            $rejectionRate = ConversionMetrics::rejectionRate(
                (int) $o['open_count'],
                (int) $o['confirmed_count'],
                (int) $o['paid_count'],
                (int) $o['rejected_count']
            );
            $enriched[] = [
                'offer_id' => (int) $o['offer_id'],
                'offer_name' => (string) $o['offer_name'],
                'clicks' => $clicks,
                'conversions' => $conversions,
                'cost' => $cost,
                'revenue' => (float) $o['revenue_total'],
                'profit' => $profit,
                'cr' => $cr,
                'roi' => $roi,
                'rejection_rate' => $rejectionRate,
            ];
        }

        $byRoi = $enriched;
        usort($byRoi, static function (array $a, array $b): int {
            $ra = $a['roi'];
            $rb = $b['roi'];
            if ($ra === null && $rb === null) {
                return 0;
            }
            if ($ra === null) {
                return 1;
            }
            if ($rb === null) {
                return -1;
            }

            return $rb <=> $ra;
        });
        $byRoi = array_slice($byRoi, 0, $limit);

        $byCr = $enriched;
        usort($byCr, static fn(array $a, array $b): int => $b['cr'] <=> $a['cr']);
        $byCr = array_slice($byCr, 0, $limit);

        $byProfit = $enriched;
        usort($byProfit, static fn(array $a, array $b): int => $b['profit'] <=> $a['profit']);
        $byProfit = array_slice($byProfit, 0, $limit);

        return [
            'by_roi' => $byRoi,
            'by_cr' => $byCr,
            'by_profit' => $byProfit,
        ];
    }
}
