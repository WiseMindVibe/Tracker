<?php

class ModelDashboard
{
    private const CONV_STATUSES = "('open','confirmed','paid','rejected','delayed')";

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
     * @return array<string, float|int>
     */
    public static function fetchAggregateStats(string $start, string $end): array
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
            WHERE c.created_at >= :start AND c.created_at <= :end
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cost = (float) ($row['cost'] ?? 0);
        $revenue = (float) ($row['revenue_all_statuses'] ?? 0);
        $profit = $revenue - $cost;
        $roi = $cost > 0 ? ($profit / $cost) * 100.0 : null;

        return [
            'total_clicks' => (int) ($row['total_clicks'] ?? 0),
            'total_conversions' => (int) ($row['total_conversions'] ?? 0),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'open_sum' => (float) ($row['open_sum'] ?? 0),
            'confirmed_count' => (int) ($row['confirmed_count'] ?? 0),
            'confirmed_sum' => (float) ($row['confirmed_sum'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'paid_sum' => (float) ($row['paid_sum'] ?? 0),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'rejected_sum' => (float) ($row['rejected_sum'] ?? 0),
            'delayed_count' => (int) ($row['delayed_count'] ?? 0),
            'delayed_sum' => (float) ($row['delayed_sum'] ?? 0),
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'roi' => $roi,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchAffiliateBreakdown(string $start, string $end): array
    {
        $sql = "
            SELECT
                aa.id AS affiliate_id,
                COALESCE(aa.affiliate_program, 'Unassigned') AS affiliate_name,
                COUNT(c.id) AS clicks,
                SUM(CASE WHEN cv.status IN " . self::CONV_STATUSES . " THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(c.cost), 0) AS cost,
                COALESCE(SUM(cv.payout), 0) AS revenue
            FROM clicks c
            LEFT JOIN conversions cv ON cv.click_internal_id = c.id
            LEFT JOIN offers o ON o.id = c.offer_id
            LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
            WHERE c.created_at >= :start AND c.created_at <= :end
            GROUP BY aa.id, aa.affiliate_program
            HAVING COUNT(c.id) > 0
                OR SUM(CASE WHEN cv.status IN " . self::CONV_STATUSES . " THEN 1 ELSE 0 END) > 0
                OR COALESCE(SUM(c.cost), 0) > 0
                OR COALESCE(SUM(cv.payout), 0) > 0
            ORDER BY (COALESCE(SUM(cv.payout), 0) - COALESCE(SUM(c.cost), 0)) DESC, COUNT(c.id) DESC
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $cost = (float) ($r['cost'] ?? 0);
            $revenue = (float) ($r['revenue'] ?? 0);
            $clicks = (int) ($r['clicks'] ?? 0);
            $conversions = (int) ($r['conversions'] ?? 0);
            $r['profit'] = $revenue - $cost;
            $r['cr'] = $clicks > 0 ? ($conversions / $clicks) * 100.0 : 0.0;
            $r['roi'] = $cost > 0 ? (($revenue - $cost) / $cost) * 100.0 : null;
        }
        unset($r);

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

        $sql = "
            SELECT
                o.id AS offer_id,
                o.name AS offer_name,
                COUNT(c.id) AS clicks,
                SUM(CASE WHEN cv.status IN " . self::CONV_STATUSES . " THEN 1 ELSE 0 END) AS conversions,
                COALESCE(SUM(c.cost), 0) AS cost,
                COALESCE(SUM(cv.payout), 0) AS revenue
            FROM clicks c
            LEFT JOIN conversions cv ON cv.click_internal_id = c.id
            INNER JOIN offers o ON o.id = c.offer_id
            WHERE c.created_at >= :start AND c.created_at <= :end
            GROUP BY o.id, o.name
            HAVING COUNT(c.id) > 0
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':start' => $start->format('Y-m-d') . ' 00:00:00',
            ':end' => $end->format('Y-m-d') . ' 23:59:59',
        ]);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $enriched = [];
        foreach ($raw as $r) {
            $cost = (float) ($r['cost'] ?? 0);
            $revenue = (float) ($r['revenue'] ?? 0);
            $clicks = (int) ($r['clicks'] ?? 0);
            $conversions = (int) ($r['conversions'] ?? 0);
            $profit = $revenue - $cost;
            $cr = $clicks > 0 ? ($conversions / $clicks) * 100.0 : 0.0;
            $roi = $cost > 0 ? (($revenue - $cost) / $cost) * 100.0 : null;
            $enriched[] = array_merge($r, [
                'profit' => $profit,
                'cr' => $cr,
                'roi' => $roi,
            ]);
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
