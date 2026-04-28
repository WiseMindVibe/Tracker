<?php

final class ReportingRepository
{
    private const SQL_SELECT = 'SELECT ';
    private const SQL_FROM_CLICKS = 'FROM clicks c ';
    private const SQL_WHERE = ' WHERE ';
    private const SQL_OS_NORMALIZED = "COALESCE(NULLIF(c.`OS`, ''), '(none)')";
    private const SQL_BROWSER_NORMALIZED = "COALESCE(NULLIF(c.browser, ''), '(none)')";
    private const SQL_COUNTRY_NORMALIZED = "COALESCE(NULLIF(c.country, ''), '(none)')";
    private const SQL_REGION_NORMALIZED = "COALESCE(NULLIF(c.region, ''), '(none)')";
    private const SQL_LANGUAGE_NORMALIZED = "COALESCE(NULLIF(c.language, ''), '(none)')";
    private const SQL_DEVICE_NORMALIZED = "COALESCE(NULLIF(c.device, ''), '(none)')";
    private const SQL_OS_VERSION_NORMALIZED = "COALESCE(NULLIF(c.os_version, ''), '(none)')";
    private const SQL_BROWSER_VERSION_NORMALIZED = "COALESCE(NULLIF(c.browser_version, ''), '(none)')";
    private const SQL_CONNECTION_TYPE_NORMALIZED = "COALESCE(NULLIF(c.connection_type, ''), '(none)')";
    private const SQL_CARRIER_NORMALIZED = "COALESCE(NULLIF(c.carrier, ''), '(none)')";
    private const SQL_ISP_NORMALIZED = "COALESCE(NULLIF(c.isp, ''), '(none)')";
    private const SQL_ZONEID_NORMALIZED = "COALESCE(NULLIF(c.zone_id, ''), '(none)')";
    private const SQL_SUBZONE_ID_NORMALIZED = "COALESCE(NULLIF(c.subzone_id, ''), '(none)')";
    private const SQL_EXTERNAL_CAMPAIGN_ID_NORMALIZED = "COALESCE(NULLIF(cei_agg.ext_min, ''), '(none)')";

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    /**
     * @param array<string, int|string> $parentFilters
     * @return list<array<string, mixed>>
     */
    public function fetchGroupedClicks(
        string $groupBy,
        string $dateFrom,
        string $dateTo,
        array $parentFilters
    ): array {
        $dim = $this->dimensionConfig($groupBy);
        [$joinSql, $whereSql, $params] = $this->buildSqlContext($dateFrom, $dateTo, $groupBy, $parentFilters);

        $sql = 'SELECT '
            . $dim['group_key_sql'] . ' AS group_key, '
            . $dim['group_name_sql'] . ' AS group_name, '
            . 'COUNT(c.id) AS clicks, '
            . 'COALESCE(SUM(c.cost), 0) AS spent '
            . self::SQL_FROM_CLICKS
            . $joinSql
            . self::SQL_WHERE . $whereSql
            . ' GROUP BY ' . $dim['group_by_sql']
            . ' ORDER BY clicks DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @param array<string, int|string> $parentFilters
     * @return list<array<string, mixed>>
     */
    public function fetchGroupedConversions(
        string $groupBy,
        string $dateFrom,
        string $dateTo,
        array $parentFilters
    ): array {
        $dim = $this->dimensionConfig($groupBy);
        [$joinSql, $whereSql, $params] = $this->buildSqlContext($dateFrom, $dateTo, $groupBy, $parentFilters);

        $sql = self::SQL_SELECT
            . $dim['group_key_sql'] . ' AS group_key, '
            . 'COUNT(cv.id) AS total_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 1 THEN 1 ELSE 0 END), 0) AS open_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 1 THEN cv.revenue ELSE 0 END), 0) AS open_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 2 THEN 1 ELSE 0 END), 0) AS confirmed_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 2 THEN cv.revenue ELSE 0 END), 0) AS confirmed_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 3 THEN 1 ELSE 0 END), 0) AS rejected_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 3 THEN cv.revenue ELSE 0 END), 0) AS rejected_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 4 THEN 1 ELSE 0 END), 0) AS paid_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 4 THEN cv.revenue ELSE 0 END), 0) AS paid_conversions_sum '
            . self::SQL_FROM_CLICKS
            . $joinSql
            . ' INNER JOIN conversions cv ON cv.click_id = c.id AND cv.status IN (1,2,3,4) '
            . self::SQL_WHERE . $whereSql
            . ' GROUP BY ' . $dim['group_by_sql']
            . ' ORDER BY total_conversions DESC';

        return $this->fetchAll($sql, $params);
    }

    /**
     * @param array<string, int|string> $parentFilters
     * @return array<string, mixed>
     */
    public function fetchTotalsClicks(
        string $dateFrom,
        string $dateTo,
        array $parentFilters
    ): array {
        [$joinSql, $whereSql, $params] = $this->buildSqlContext($dateFrom, $dateTo, null, $parentFilters);

        $sql = self::SQL_SELECT
            . 'COUNT(c.id) AS clicks, '
            . 'COALESCE(SUM(c.cost), 0) AS spent '
            . self::SQL_FROM_CLICKS
            . $joinSql
            . self::SQL_WHERE . $whereSql;

        return $this->fetchOne($sql, $params);
    }

    /**
     * @param array<string, int|string> $parentFilters
     * @return array<string, mixed>
     */
    public function fetchTotalsConversions(
        string $dateFrom,
        string $dateTo,
        array $parentFilters
    ): array {
        [$joinSql, $whereSql, $params] = $this->buildSqlContext($dateFrom, $dateTo, null, $parentFilters);

        $sql = self::SQL_SELECT
            . 'COUNT(cv.id) AS total_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 1 THEN 1 ELSE 0 END), 0) AS open_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 1 THEN cv.revenue ELSE 0 END), 0) AS open_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 2 THEN 1 ELSE 0 END), 0) AS confirmed_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 2 THEN cv.revenue ELSE 0 END), 0) AS confirmed_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 3 THEN 1 ELSE 0 END), 0) AS rejected_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 3 THEN cv.revenue ELSE 0 END), 0) AS rejected_conversions_sum, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 4 THEN 1 ELSE 0 END), 0) AS paid_conversions, '
            . 'COALESCE(SUM(CASE WHEN cv.status = 4 THEN cv.revenue ELSE 0 END), 0) AS paid_conversions_sum '
            . self::SQL_FROM_CLICKS
            . $joinSql
            . ' INNER JOIN conversions cv ON cv.click_id = c.id AND cv.status IN (1,2,3,4) '
            . self::SQL_WHERE . $whereSql;

        return $this->fetchOne($sql, $params);
    }

    /**
     * @return array{
     *   group_key_sql: string,
     *   group_name_sql: string,
     *   group_by_sql: string
     * }
     */
    private function dimensionConfig(string $groupBy): array
    {
        return match ($groupBy) {
            'offer' => [
                'group_key_sql' => 'COALESCE(c.offer_id, 0)',
                'group_name_sql' => "COALESCE(MAX(o.name), '(no offer)')",
                'group_by_sql' => 'COALESCE(c.offer_id, 0)',
            ],
            'campaign' => [
                'group_key_sql' => 'COALESCE(c.campaign_id, 0)',
                'group_name_sql' => "COALESCE(MAX(cam.name), '(no campaign)')",
                'group_by_sql' => 'COALESCE(c.campaign_id, 0)',
            ],
            'external_campaign_id' => [
                'group_key_sql' => self::SQL_EXTERNAL_CAMPAIGN_ID_NORMALIZED,
                'group_name_sql' => self::SQL_EXTERNAL_CAMPAIGN_ID_NORMALIZED,
                'group_by_sql' => self::SQL_EXTERNAL_CAMPAIGN_ID_NORMALIZED,
            ],
            'os' => [
                'group_key_sql' => self::SQL_OS_NORMALIZED,
                'group_name_sql' => self::SQL_OS_NORMALIZED,
                'group_by_sql' => self::SQL_OS_NORMALIZED,
            ],
            'browser' => [
                'group_key_sql' => self::SQL_BROWSER_NORMALIZED,
                'group_name_sql' => self::SQL_BROWSER_NORMALIZED,
                'group_by_sql' => self::SQL_BROWSER_NORMALIZED,
            ],
            'country' => [
                'group_key_sql' => self::SQL_COUNTRY_NORMALIZED,
                'group_name_sql' => self::SQL_COUNTRY_NORMALIZED,
                'group_by_sql' => self::SQL_COUNTRY_NORMALIZED,
            ],
            'region' => [
                'group_key_sql' => self::SQL_REGION_NORMALIZED,
                'group_name_sql' => self::SQL_REGION_NORMALIZED,
                'group_by_sql' => self::SQL_REGION_NORMALIZED,
            ],
            'language' => [
                'group_key_sql' => self::SQL_LANGUAGE_NORMALIZED,
                'group_name_sql' => self::SQL_LANGUAGE_NORMALIZED,
                'group_by_sql' => self::SQL_LANGUAGE_NORMALIZED,
            ],
            'device' => [
                'group_key_sql' => self::SQL_DEVICE_NORMALIZED,
                'group_name_sql' => self::SQL_DEVICE_NORMALIZED,
                'group_by_sql' => self::SQL_DEVICE_NORMALIZED,
            ],
            'os_version' => [
                'group_key_sql' => self::SQL_OS_VERSION_NORMALIZED,
                'group_name_sql' => self::SQL_OS_VERSION_NORMALIZED,
                'group_by_sql' => self::SQL_OS_VERSION_NORMALIZED,
            ],
            'browser_version' => [
                'group_key_sql' => self::SQL_BROWSER_VERSION_NORMALIZED,
                'group_name_sql' => self::SQL_BROWSER_VERSION_NORMALIZED,
                'group_by_sql' => self::SQL_BROWSER_VERSION_NORMALIZED,
            ],
            'connection_type' => [
                'group_key_sql' => self::SQL_CONNECTION_TYPE_NORMALIZED,
                'group_name_sql' => self::SQL_CONNECTION_TYPE_NORMALIZED,
                'group_by_sql' => self::SQL_CONNECTION_TYPE_NORMALIZED,
            ],
            'carrier' => [
                'group_key_sql' => self::SQL_CARRIER_NORMALIZED,
                'group_name_sql' => self::SQL_CARRIER_NORMALIZED,
                'group_by_sql' => self::SQL_CARRIER_NORMALIZED,
            ],
            'isp' => [
                'group_key_sql' => self::SQL_ISP_NORMALIZED,
                'group_name_sql' => self::SQL_ISP_NORMALIZED,
                'group_by_sql' => self::SQL_ISP_NORMALIZED,
            ],
            'zoneid' => [
                'group_key_sql' => self::SQL_ZONEID_NORMALIZED,
                'group_name_sql' => self::SQL_ZONEID_NORMALIZED,
                'group_by_sql' => self::SQL_ZONEID_NORMALIZED,
            ],
            'subzone_id' => [
                'group_key_sql' => self::SQL_SUBZONE_ID_NORMALIZED,
                'group_name_sql' => self::SQL_SUBZONE_ID_NORMALIZED,
                'group_by_sql' => self::SQL_SUBZONE_ID_NORMALIZED,
            ],
            default => throw new InvalidArgumentException('Unsupported group: ' . $groupBy),
        };
    }

    /**
     * @param array<string, int|string> $parentFilters
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function buildSqlContext(
        string $dateFrom,
        string $dateTo,
        ?string $groupBy,
        array $parentFilters
    ): array {
        $needsOffers = $groupBy === 'offer';
        $needsCampaign = $groupBy === 'campaign';
        $needsExternalCampaignIds = $groupBy === 'external_campaign_id' || array_key_exists('external_campaign_id', $parentFilters);

        $joins = [];
        if ($needsOffers) {
            $joins[] = 'LEFT JOIN offers o ON o.id = c.offer_id';
        }
        if ($needsCampaign) {
            $joins[] = 'LEFT JOIN campaigns cam ON cam.id = c.campaign_id';
        }
        if ($needsExternalCampaignIds) {
            $joins[] = 'LEFT JOIN (SELECT campaign_id, MIN(external_campaign_id) AS ext_min FROM campaign_external_ids GROUP BY campaign_id) cei_agg ON cei_agg.campaign_id = c.campaign_id';
        }

        $params = [
            ':date_from' => $dateFrom . ' 00:00:00',
            ':date_to' => $dateTo . ' 23:59:59',
        ];
        $where = [
            'c.created_at >= :date_from',
            'c.created_at <= :date_to',
        ];

        foreach ($parentFilters as $key => $value) {
            $this->appendParentFilter($key, $value, $where, $params);
        }

        $joinSql = $joins === [] ? '' : ' ' . implode(' ', $joins) . ' ';

        return [$joinSql, implode(' AND ', $where), $params];
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function appendParentFilter(string $key, int|string $value, array &$where, array &$params): void
    {
        switch ($key) {
            case 'offer_id':
                $offerId = (int) $value;
                if ($offerId > 0) {
                    $where[] = 'c.offer_id = :parent_offer_id';
                    $params[':parent_offer_id'] = $offerId;
                } else {
                    $where[] = 'COALESCE(c.offer_id, 0) = 0';
                }
                break;

            case 'campaign_id':
                $campaignId = (int) $value;
                if ($campaignId > 0) {
                    $where[] = 'c.campaign_id = :parent_campaign_id';
                    $params[':parent_campaign_id'] = $campaignId;
                } else {
                    $where[] = 'COALESCE(c.campaign_id, 0) = 0';
                }
                break;

            case 'os':
                $os = (string) $value;
                if ($os === '(none)') {
                    $where[] = '(c.`OS` IS NULL OR c.`OS` = \'\')';
                } else {
                    $where[] = 'c.`OS` = :parent_os';
                    $params[':parent_os'] = $os;
                }
                break;

            case 'browser':
                $browser = (string) $value;
                if ($browser === '(none)') {
                    $where[] = '(c.browser IS NULL OR c.browser = \'\')';
                } else {
                    $where[] = 'c.browser = :parent_browser';
                    $params[':parent_browser'] = $browser;
                }
                break;

            case 'external_campaign_id':
                $this->appendStringParentFilter('external_campaign_id', 'cei_agg.ext_min', $value, $where, $params);
                break;

            case 'country':
                $this->appendStringParentFilter('country', 'c.country', $value, $where, $params);
                break;

            case 'region':
                $this->appendStringParentFilter('region', 'c.region', $value, $where, $params);
                break;

            case 'language':
                $this->appendStringParentFilter('language', 'c.language', $value, $where, $params);
                break;

            case 'device':
                $this->appendStringParentFilter('device', 'c.device', $value, $where, $params);
                break;

            case 'os_version':
                $this->appendStringParentFilter('os_version', 'c.os_version', $value, $where, $params);
                break;

            case 'browser_version':
                $this->appendStringParentFilter('browser_version', 'c.browser_version', $value, $where, $params);
                break;

            case 'connection_type':
                $this->appendStringParentFilter('connection_type', 'c.connection_type', $value, $where, $params);
                break;

            case 'carrier':
                $this->appendStringParentFilter('carrier', 'c.carrier', $value, $where, $params);
                break;

            case 'isp':
                $this->appendStringParentFilter('isp', 'c.isp', $value, $where, $params);
                break;

            case 'zoneid':
                $this->appendStringParentFilter('zoneid', 'c.zone_id', $value, $where, $params);
                break;

            case 'subzone_id':
                $this->appendStringParentFilter('subzone_id', 'c.subzone_id', $value, $where, $params);
                break;

            default:
                throw new InvalidArgumentException('Unsupported parent filter: ' . $key);
        }
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function appendStringParentFilter(
        string $key,
        string $column,
        int|string $value,
        array &$where,
        array &$params
    ): void {
        $stringValue = (string) $value;
        if ($stringValue === '(none)') {
            $where[] = '(' . $column . ' IS NULL OR ' . $column . ' = \'\')';
            return;
        }

        $param = ':parent_' . $key;
        $where[] = $column . ' = ' . $param;
        $params[$param] = $stringValue;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function fetchOne(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }
}
