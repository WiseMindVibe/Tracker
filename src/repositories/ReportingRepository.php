<?php

final class ReportingRepository
{
    private const SQL_SELECT = 'SELECT ';
    private const SQL_FROM_CLICKS = 'FROM clicks c ';
    private const SQL_WHERE = ' WHERE ';
    private const SQL_OS_NORMALIZED = "COALESCE(NULLIF(c.`OS`, ''), '(none)')";
    private const SQL_BROWSER_NORMALIZED = "COALESCE(NULLIF(c.browser, ''), '(none)')";

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

        $joins = [];
        if ($needsOffers) {
            $joins[] = 'LEFT JOIN offers o ON o.id = c.offer_id';
        }
        if ($needsCampaign) {
            $joins[] = 'LEFT JOIN campaigns cam ON cam.id = c.campaign_id';
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

            default:
                throw new InvalidArgumentException('Unsupported parent filter: ' . $key);
        }
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
