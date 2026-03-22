<?php

class ReportingModel
{
    private const CONV_ALL = "('open','confirmed','paid','rejected','delayed')";

    private static string $table = 'clicks t';

    /** @var list<string> */
    private static array $joins = [
        'LEFT JOIN offers o ON o.id = t.offer_id',
        'LEFT JOIN campaigns cam ON cam.uuid = t.campaign_id OR cam.id = t.campaign_id',
        'LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id',
        'LEFT JOIN conversions cv ON cv.click_internal_id = t.id',
    ];

    /**
     * Group key => SQL expression (must match GROUP BY expression).
     *
     * @var array<string, string>
     */
    public static array $groupsMap = [
        'offer' => "COALESCE(o.name, '(no offer)')",
        'campaign' => "COALESCE(cam.name, '(no campaign)')",
        // No clicks.external_campaign_id in your schema: one value per click via scalar subquery (never JOIN cei — 1:N duplicates rows).
        'external_campaign' => "NULLIF(TRIM((SELECT MIN(cei.external_campaign_id) FROM campaign_external_ids cei WHERE cei.campaign_id = t.campaign_id)), '')",
        'affiliate' => "COALESCE(aa.affiliate_program, '(unassigned)')",
        'device' => 't.device',
        'os' => 't.`OS`',
        'os_version' => 't.os_version',
        'browser' => 't.browser',
        'browser_version' => 't.browser_version',
        'country' => 't.country',
        'region' => 't.region',
        'language' => 't.language',
        'connection_type' => 't.connection_type',
        'isp' => 't.isp',
        'carrier' => 't.carrier',
        'zone' => 't.zone_id',
        'subzone' => 't.subzone_id',
        'banner' => 't.bannerid',
    ];

    /**
     * Raw aggregates only; derived metrics (profit, cr, roi, …) are computed in the controller.
     *
     * @var array<string, string>
     */
    public static array $metrics = [
        'total_clicks' => 'COUNT(t.id)',
        'conversions_count' => 'SUM(CASE WHEN cv.status IN ' . self::CONV_ALL . ' THEN 1 ELSE 0 END)',
        'conversions_sum' => 'SUM(CASE WHEN cv.status IN ' . self::CONV_ALL . ' THEN cv.payout ELSE 0 END)',
        'open_amount' => "SUM(CASE WHEN cv.status = 'open' THEN 1 ELSE 0 END)",
        'open_sum' => "SUM(CASE WHEN cv.status = 'open' THEN cv.payout ELSE 0 END)",
        'confirm_amount' => "SUM(CASE WHEN cv.status = 'confirmed' THEN 1 ELSE 0 END)",
        'confirm_sum' => "SUM(CASE WHEN cv.status = 'confirmed' THEN cv.payout ELSE 0 END)",
        'paid_amount' => "SUM(CASE WHEN cv.status = 'paid' THEN 1 ELSE 0 END)",
        'paid_sum' => "SUM(CASE WHEN cv.status = 'paid' THEN cv.payout ELSE 0 END)",
        'reject_amount' => "SUM(CASE WHEN cv.status = 'rejected' THEN 1 ELSE 0 END)",
        'reject_sum' => "SUM(CASE WHEN cv.status = 'rejected' THEN cv.payout ELSE 0 END)",
        'spent' => 'COALESCE(SUM(t.cost), 0)',
        // Revenue for P&L / ROI: non-rejected payouts only
        'revenue' => "COALESCE(SUM(CASE WHEN cv.status IN ('open','confirmed','paid','delayed') THEN cv.payout ELSE 0 END), 0)",
    ];

    /**
     * @param list<string> $groups
     * @return list<array<string, mixed>>
     */
    public static function getReport(array $groups, string $start, string $end): array
    {
        $validGroups = [];
        foreach ($groups as $g) {
            $g = trim((string) $g);
            if ($g !== '' && isset(self::$groupsMap[$g])) {
                $validGroups[] = $g;
            }
        }

        $groupSql = [];
        foreach ($validGroups as $g) {
            $expr = self::$groupsMap[$g];
            $groupSql[] = $expr . ' AS `' . $g . '`';
        }

        $metricSql = [];
        foreach (self::$metrics as $alias => $sql) {
            $metricSql[] = $sql . ' AS `' . $alias . '`';
        }

        $sql = 'SELECT ';
        if ($groupSql !== []) {
            $sql .= implode(',', $groupSql) . ',';
        }
        $sql .= implode(',', $metricSql);
        $sql .= ' FROM ' . self::$table;
        foreach (self::$joins as $join) {
            $sql .= ' ' . $join;
        }
        $sql .= ' WHERE t.created_at >= :start AND t.created_at <= :end ';
        if ($validGroups !== []) {
            $groupBy = array_map(static fn(string $g): string => self::$groupsMap[$g], $validGroups);
            $sql .= ' GROUP BY ' . implode(',', $groupBy);
        }
        $sql .= ' ORDER BY total_clicks DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
