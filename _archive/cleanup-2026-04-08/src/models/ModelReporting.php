<?php

/**
 * Reporting: per-click conversion aggregates (no join inflation), then GROUP BY chosen dimensions.
 *
 * Conversion statuses: tinyint 0 unknown, 1 open, 2 confirmed, 3 rejected, 4 paid (@see migration 004.1).
 */
class ReportingModel
{
    private static string $table = 'clicks t';

    /** @var list<string> */
    private static array $baseJoins = [
        'LEFT JOIN offers o ON o.id = t.offer_id',
        'LEFT JOIN campaigns cam ON cam.uuid = t.campaign_id OR cam.id = t.campaign_id',
        'LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id',
        'LEFT JOIN (
            SELECT campaign_id, MIN(external_campaign_id) AS ext_min
            FROM campaign_external_ids
            GROUP BY campaign_id
        ) cei_agg ON cei_agg.campaign_id = cam.id',
    ];

    private static string $convAggJoin = <<<'SQL'
LEFT JOIN (
    SELECT click_id,
        SUM(CASE WHEN status IN (1,2,3,4) THEN 1 ELSE 0 END) AS conv_cnt,
        SUM(CASE WHEN status IN (1,2,3,4) THEN revenue ELSE 0 END) AS conv_sum,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS open_n,
        SUM(CASE WHEN status = 1 THEN revenue ELSE 0 END) AS open_sum,
        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS confirm_n,
        SUM(CASE WHEN status = 2 THEN revenue ELSE 0 END) AS confirm_sum,
        SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS paid_n,
        SUM(CASE WHEN status = 4 THEN revenue ELSE 0 END) AS paid_sum,
        SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS reject_n,
        SUM(CASE WHEN status = 3 THEN revenue ELSE 0 END) AS reject_sum,
        SUM(CASE WHEN status != 3 THEN revenue ELSE 0 END) AS revenue_excl_reject
    FROM conversions
    GROUP BY click_id
) cva ON cva.click_id = t.id
SQL;

    /**
     * Dimension => GROUP BY expression (must match filter predicates).
     * Keys listed here are valid breakdowns (banner removed — no column in schema).
     *
     * @var array<string, string>
     */
    public static array $groupsMap = [
        'offer' => 'o.id',
        'campaign' => 'COALESCE(cam.id, 0)',
        'external_campaign' => "COALESCE(NULLIF(TRIM(cei_agg.ext_min), ''), '')",
        'affiliate' => 'COALESCE(aa.id, 0)',
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
    ];

    /** @var array<string, string> */
    private static array $metricsOuter = [
        'total_clicks' => 'COUNT(t.id)',
        'conversions_count' => 'COALESCE(SUM(cva.conv_cnt), 0)',
        'conversions_sum' => 'COALESCE(SUM(cva.conv_sum), 0)',
        'open_amount' => 'COALESCE(SUM(cva.open_n), 0)',
        'open_sum' => 'COALESCE(SUM(cva.open_sum), 0)',
        'confirm_amount' => 'COALESCE(SUM(cva.confirm_n), 0)',
        'confirm_sum' => 'COALESCE(SUM(cva.confirm_sum), 0)',
        'paid_amount' => 'COALESCE(SUM(cva.paid_n), 0)',
        'paid_sum' => 'COALESCE(SUM(cva.paid_sum), 0)',
        'reject_amount' => 'COALESCE(SUM(cva.reject_n), 0)',
        'reject_sum' => 'COALESCE(SUM(cva.reject_sum), 0)',
        'spent' => 'COALESCE(SUM(t.cost), 0)',
        'revenue' => 'COALESCE(SUM(cva.revenue_excl_reject), 0)',
    ];

    /**
     * SELECT fragments: group expression, key alias, label expression (aggregated).
     *
     * @return array{0: string, 1: string, 2: string}|null
     */
    private static function dimensionSelectParts(string $dim): ?array
    {
        return match ($dim) {
            'offer' => [
                'o.id',
                'offer_key',
                "MAX(COALESCE(o.name, '(no offer)')) AS `offer`",
            ],
            'campaign' => [
                'COALESCE(cam.id, 0)',
                'campaign_key',
                "MAX(COALESCE(cam.name, '(no campaign)')) AS `campaign`",
            ],
            'external_campaign' => [
                "COALESCE(NULLIF(TRIM(cei_agg.ext_min), ''), '')",
                'external_campaign_key',
                "MAX(CASE WHEN cei_agg.ext_min IS NULL OR NULLIF(TRIM(cei_agg.ext_min), '') IS NULL THEN '(none)' ELSE cei_agg.ext_min END) AS `external_campaign`",
            ],
            'affiliate' => [
                'COALESCE(aa.id, 0)',
                'affiliate_key',
                "MAX(COALESCE(aa.affiliate_program, '(unassigned)')) AS `affiliate`",
            ],
            'device' => ['t.device', 'device_key', 'MAX(t.device) AS `device`'],
            'os' => ['t.`OS`', 'os_key', 'MAX(t.`OS`) AS `os`'],
            'os_version' => ['t.os_version', 'os_version_key', 'MAX(t.os_version) AS `os_version`'],
            'browser' => ['t.browser', 'browser_key', 'MAX(t.browser) AS `browser`'],
            'browser_version' => ['t.browser_version', 'browser_version_key', 'MAX(t.browser_version) AS `browser_version`'],
            'country' => ['t.country', 'country_key', 'MAX(t.country) AS `country`'],
            'region' => ['t.region', 'region_key', 'MAX(t.region) AS `region`'],
            'language' => ['t.language', 'language_key', 'MAX(t.language) AS `language`'],
            'connection_type' => ['t.connection_type', 'connection_type_key', 'MAX(t.connection_type) AS `connection_type`'],
            'isp' => ['t.isp', 'isp_key', 'MAX(t.isp) AS `isp`'],
            'carrier' => ['t.carrier', 'carrier_key', 'MAX(t.carrier) AS `carrier`'],
            'zone' => ['t.zone_id', 'zone_key', 'MAX(t.zone_id) AS `zone`'],
            'subzone' => ['t.subzone_id', 'subzone_key', 'MAX(t.subzone_id) AS `subzone`'],
            default => null,
        };
    }

    /**
     * Append WHERE fragment and params for one path segment (parent filter).
     *
     * @param array{dim: string, key: string} $segment
     * @param array<string, mixed>          $params
     */
    private static function appendPathFilter(string $dim, array $segment, array &$params, int $idx): string
    {
        $key = (string) ($segment['key'] ?? '');
        $pname = ':pf' . $idx;

        return match ($dim) {
            'offer' => self::filterNumericOrZero('o.id', $key, $pname, $params),
            'campaign' => self::filterNumericOrZero('COALESCE(cam.id, 0)', $key, $pname, $params),
            'affiliate' => self::filterNumericOrZero('COALESCE(aa.id, 0)', $key, $pname, $params),
            'external_campaign' => self::filterExternalCampaign($key, $pname, $params),
            'device' => self::filterStringCol('t.device', $key, $pname, $params),
            'os' => self::filterStringCol('t.`OS`', $key, $pname, $params),
            'os_version' => self::filterStringCol('t.os_version', $key, $pname, $params),
            'browser' => self::filterStringCol('t.browser', $key, $pname, $params),
            'browser_version' => self::filterStringCol('t.browser_version', $key, $pname, $params),
            'country' => self::filterStringCol('t.country', $key, $pname, $params),
            'region' => self::filterStringCol('t.region', $key, $pname, $params),
            'language' => self::filterStringCol('t.language', $key, $pname, $params),
            'connection_type' => self::filterStringCol('t.connection_type', $key, $pname, $params),
            'isp' => self::filterStringCol('t.isp', $key, $pname, $params),
            'carrier' => self::filterStringCol('t.carrier', $key, $pname, $params),
            'zone' => self::filterStringCol('t.zone_id', $key, $pname, $params),
            'subzone' => self::filterStringCol('t.subzone_id', $key, $pname, $params),
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function filterNumericOrZero(string $expr, string $key, string $pname, array &$params): string
    {
        if ($key === '' || $key === '(none)') {
            return ' AND (' . $expr . ' = 0 OR ' . $expr . ' IS NULL) ';
        }
        if (!is_numeric($key)) {
            return ' AND 1=0 ';
        }
        $params[$pname] = (int) $key;

        return ' AND ' . $expr . ' = ' . $pname . ' ';
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function filterExternalCampaign(string $key, string $pname, array &$params): string
    {
        if ($key === '' || $key === '(none)') {
            return ' AND (cei_agg.ext_min IS NULL OR NULLIF(TRIM(cei_agg.ext_min), \'\') IS NULL) ';
        }
        $params[$pname] = $key;

        return ' AND cei_agg.ext_min = ' . $pname . ' ';
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function filterStringCol(string $colExpr, string $key, string $pname, array &$params): string
    {
        if ($key === '' || $key === '(none)') {
            return ' AND (' . $colExpr . " = '' OR " . $colExpr . ' IS NULL) ';
        }
        $params[$pname] = $key;

        return ' AND ' . $colExpr . ' = ' . $pname . ' ';
    }

    /**
     * @param list<string> $groups
     * @param list<array{dim: string, key: string}> $path
     * @param array<string, mixed> $params
     */
    private static function buildPathWhere(array $groups, array $path, int $depth, array &$params): string
    {
        if ($path === [] || $depth < 2) {
            return '';
        }
        $need = $depth - 1;
        if (count($path) !== $need) {
            return ' AND 1=0 ';
        }
        $sql = '';
        for ($i = 0; $i < $need; $i++) {
            $dim = $path[$i]['dim'] ?? '';
            if ($dim !== ($groups[$i] ?? '')) {
                return ' AND 1=0 ';
            }
            $sql .= self::appendPathFilter($dim, $path[$i], $params, $i);
        }

        return $sql;
    }

    /**
     * @param list<string> $groups
     * @return list<string>
     */
    private static function normalizeGroups(array $groups): array
    {
        $validGroups = [];
        foreach ($groups as $g) {
            $g = trim((string) $g);
            if ($g !== '' && isset(self::$groupsMap[$g])) {
                $validGroups[] = $g;
            }
        }

        return array_values(array_unique($validGroups));
    }

    /**
     * @param list<array<string, mixed>> $path
     * @return list<array{dim: string, key: string}>
     */
    public static function normalizePath(array $groups, array $path): array
    {
        $out = [];
        foreach ($path as $seg) {
            if (!is_array($seg)) {
                continue;
            }
            $dim = trim((string) ($seg['dim'] ?? ''));
            if ($dim === '' || !isset(self::$groupsMap[$dim])) {
                continue;
            }
            $out[] = [
                'dim' => $dim,
                'key' => (string) ($seg['key'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Grand totals for the date range (full window, not limited to loaded drill rows).
     *
     * @return array<string, mixed>
     */
    public static function getGrandTotals(string $start, string $end): array
    {
        $metricSql = [];
        foreach (self::$metricsOuter as $alias => $sql) {
            $metricSql[] = $sql . ' AS `' . $alias . '`';
        }
        $sql = 'SELECT ' . implode(',', $metricSql)
            . ' FROM ' . self::$table;
        foreach (self::$baseJoins as $join) {
            $sql .= ' ' . $join;
        }
        $sql .= ' ' . trim(self::$convAggJoin);
        $sql .= ' WHERE t.created_at >= :start AND t.created_at <= :end ';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @param list<string> $groups
     * @param list<array{dim: string, key: string}> $path
     * @return list<array<string, mixed>>
     */
    public static function getReportSlice(
        array $groups,
        string $start,
        string $end,
        int $depth,
        array $path = [],
        ?string $orderBy = null,
        string $orderDir = 'DESC'
    ): array {
        $validGroups = self::normalizeGroups($groups);
        if ($validGroups === []) {
            $validGroups = ['offer'];
        }
        $depth = max(1, min($depth, count($validGroups)));
        $path = self::normalizePath($validGroups, $path);
        if (count($path) !== $depth - 1) {
            return [];
        }

        $sliceGroups = array_slice($validGroups, 0, $depth);
        $selectDims = [];
        $groupBy = [];
        foreach ($sliceGroups as $dim) {
            $parts = self::dimensionSelectParts($dim);
            if ($parts === null) {
                continue;
            }
            [$gexpr, $keyAlias, $labelSql] = $parts;
            $selectDims[] = $gexpr . ' AS `' . $keyAlias . '`';
            $selectDims[] = $labelSql;
            $groupBy[] = $gexpr;
        }

        if ($groupBy === []) {
            return [];
        }

        $metricSql = [];
        foreach (self::$metricsOuter as $alias => $sql) {
            $metricSql[] = $sql . ' AS `' . $alias . '`';
        }

        $params = [
            ':start' => $start . ' 00:00:00',
            ':end' => $end . ' 23:59:59',
        ];
        $pathWhere = self::buildPathWhere($validGroups, $path, $depth, $params);

        $sql = 'SELECT ' . implode(',', $selectDims) . ',' . implode(',', $metricSql)
            . ' FROM ' . self::$table;
        foreach (self::$baseJoins as $join) {
            $sql .= ' ' . $join;
        }
        $sql .= ' ' . trim(self::$convAggJoin);
        $sql .= ' WHERE t.created_at >= :start AND t.created_at <= :end ';
        $sql .= $pathWhere;
        $sql .= ' GROUP BY ' . implode(',', $groupBy);

        $keyAliases = [];
        foreach ($sliceGroups as $d) {
            $p = self::dimensionSelectParts($d);
            if ($p !== null) {
                $keyAliases[] = $p[1];
            }
        }
        $allowedOrder = array_merge(array_keys(self::$metricsOuter), $keyAliases);
        $allowedOrder = array_values(array_filter($allowedOrder, static fn (string $x): bool => $x !== ''));
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        if ($orderBy !== null && in_array($orderBy, $allowedOrder, true)) {
            $sql .= ' ORDER BY `' . str_replace('`', '``', $orderBy) . '` ' . $orderDir;
        } else {
            $sql .= ' ORDER BY total_clicks ' . $orderDir;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @deprecated Use getReportSlice + getGrandTotals
     * @param list<string> $groups
     * @return list<array<string, mixed>>
     */
    public static function getReport(array $groups, string $start, string $end): array
    {
        $validGroups = self::normalizeGroups($groups);
        if ($validGroups === []) {
            $validGroups = ['offer'];
        }

        return self::getReportSlice($validGroups, $start, $end, count($validGroups), [], null, 'DESC');
    }
}

