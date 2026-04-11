<?php

class ModelNotifications
{
    private const ALLOWED_PER_PAGE = [25, 100, 500];
    private const SOURCE_CONVERSIONS = 'conversions';
    private const SOURCE_NOTIFICATIONS = 'notifications';
    private const SOURCE_NONE = 'none';

    /** @var array<string, bool> */
    private static $tableCache = [];

    /** @var array<string, array<string, bool>> */
    private static $columnCache = [];

    /** @var ?string */
    private static $sourceCache = null;

    public static function allowedPerPage(): array
    {
        return self::ALLOWED_PER_PAGE;
    }

    public static function normalizePerPage(int $per): int
    {
        return in_array($per, self::ALLOWED_PER_PAGE, true) ? $per : 25;
    }

    public static function activeSource(): string
    {
        if (self::$sourceCache !== null) {
            return self::$sourceCache;
        }

        $hasConversions = self::hasConversionsFeed();
        $hasNotifications = self::hasNotificationsFeed();

        if ($hasConversions && self::tableHasRows('conversions')) {
            self::$sourceCache = self::SOURCE_CONVERSIONS;
            return self::$sourceCache;
        }

        if ($hasNotifications) {
            self::$sourceCache = self::SOURCE_NOTIFICATIONS;
            return self::$sourceCache;
        }

        if ($hasConversions) {
            self::$sourceCache = self::SOURCE_CONVERSIONS;
            return self::$sourceCache;
        }

        self::$sourceCache = self::SOURCE_NONE;
        return self::$sourceCache;
    }

    /**
     * Mark all notifications read when opening this page.
     * No-op when the feed is sourced from conversions.
     */
    public static function markAllRead(): void
    {
        if (self::activeSource() !== self::SOURCE_NOTIFICATIONS) {
            return;
        }
        if (!self::hasColumn('notifications', 'is_read')) {
            return;
        }

        $set = ['is_read = 1'];
        if (self::hasColumn('notifications', 'read_at')) {
            $set[] = 'read_at = NOW()';
        }

        $sql = 'UPDATE notifications SET ' . implode(', ', $set) . ' WHERE is_read = 0';
        try {
            db()->prepare($sql)->execute();
        } catch (Throwable $e) {
        }
    }

    public static function countFiltered(string $search = ''): int
    {
        if (self::activeSource() === self::SOURCE_CONVERSIONS) {
            return self::countFilteredFromConversions($search);
        }
        if (self::activeSource() === self::SOURCE_NOTIFICATIONS) {
            return self::countFilteredFromNotifications($search);
        }

        return 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchPage(int $page, int $perPage, string $search = ''): array
    {
        $perPage = self::normalizePerPage($perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        if (self::activeSource() === self::SOURCE_CONVERSIONS) {
            return self::fetchPageFromConversions($perPage, $offset, $search);
        }
        if (self::activeSource() === self::SOURCE_NOTIFICATIONS) {
            return self::fetchPageFromNotifications($perPage, $offset, $search);
        }

        return [];
    }

    private static function hasConversionsFeed(): bool
    {
        return self::hasTable('conversions')
            && self::hasColumn('conversions', 'id')
            && self::hasColumn('conversions', 'click_id');
    }

    private static function hasNotificationsFeed(): bool
    {
        return self::hasTable('notifications')
            && self::hasColumn('notifications', 'id');
    }

    private static function tableHasRows(string $table): bool
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table)) {
            return false;
        }
        if (!self::hasTable($table)) {
            return false;
        }

        try {
            $stmt = db()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function hasTable(string $table): bool
    {
        $table = strtolower(trim($table));
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }

        try {
            $stmt = db()->prepare("
                SELECT 1
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                LIMIT 1
            ");
            $stmt->execute([':table' => $table]);
            self::$tableCache[$table] = (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            self::$tableCache[$table] = false;
        }

        return self::$tableCache[$table];
    }

    /**
     * @return array<string, bool>
     */
    private static function columnsFor(string $table): array
    {
        $table = strtolower(trim($table));
        if ($table === '') {
            return [];
        }
        if (isset(self::$columnCache[$table])) {
            return self::$columnCache[$table];
        }
        if (!self::hasTable($table)) {
            self::$columnCache[$table] = [];
            return self::$columnCache[$table];
        }

        try {
            $stmt = db()->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $col) {
                $cols[strtolower((string) $col)] = true;
            }
            self::$columnCache[$table] = $cols;
        } catch (Throwable $e) {
            self::$columnCache[$table] = [];
        }

        return self::$columnCache[$table];
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $column = strtolower(trim($column));
        if ($column === '') {
            return false;
        }

        return isset(self::columnsFor($table)[$column]);
    }

    /**
     * @param list<string> $searchExprs
     * @param array<string, mixed> $params
     */
    private static function searchClause(string $search, array $searchExprs, array &$params): string
    {
        $search = trim($search);
        if ($search === '' || $searchExprs === []) {
            return '';
        }

        $params[':q'] = '%' . $search . '%';
        $parts = [];
        foreach ($searchExprs as $expr) {
            $parts[] = $expr . ' LIKE :q';
        }

        return ' AND (' . implode(' OR ', $parts) . ')';
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function fetchAllPrepared(string $sql, array $params): array
    {
        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, (int) $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function campaignNameFromClicksExpr(string $clickCampaignExpr, ?string $secondaryCampaignExpr = null, string $fallbackExpr = "'—'"): string
    {
        if (!self::hasTable('campaigns')) {
            return $fallbackExpr;
        }

        $conds = [
            "cam.uuid = {$clickCampaignExpr}",
            "cam.id = {$clickCampaignExpr}",
        ];
        if ($secondaryCampaignExpr !== null) {
            $conds[] = "cam.uuid = {$secondaryCampaignExpr}";
            $conds[] = "cam.id = {$secondaryCampaignExpr}";
        }

        $sub = '(SELECT MAX(cam.name) FROM campaigns cam WHERE ' . implode(' OR ', $conds) . ')';

        return "COALESCE({$sub}, {$fallbackExpr})";
    }

    private static function countFilteredFromConversions(string $search = ''): int
    {
        $params = [];
        $campaignExpr = self::campaignNameFromClicksExpr('c.campaign_id');

        $searchExprs = [
            'c.click_id',
            'COALESCE(o.name, \'\')',
            $campaignExpr,
            'COALESCE(aa.affiliate_program, \'\')',
        ];
        if (self::hasColumn('conversions', 'commission_id')) {
            $searchExprs[] = 'COALESCE(cv.commission_id, \'\')';
        }
        if (self::hasColumn('conversions', 'event_id')) {
            $searchExprs[] = 'COALESCE(cv.event_id, \'\')';
        }
        if (self::hasColumn('conversions', 'advertiser_id')) {
            $searchExprs[] = 'COALESCE(cv.advertiser_id, \'\')';
        }

        $where = self::searchClause($search, $searchExprs, $params);
        $sql = "
            SELECT COUNT(DISTINCT cv.id) AS cnt
            FROM conversions cv
            INNER JOIN clicks c ON c.id = cv.click_id
            LEFT JOIN offers o ON o.id = c.offer_id
            LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
            WHERE 1=1 {$where}
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fetchPageFromConversions(int $perPage, int $offset, string $search = ''): array
    {
        $params = [
            ':limit' => $perPage,
            ':offset' => $offset,
        ];
        $campaignExpr = self::campaignNameFromClicksExpr('c.campaign_id');
        $sortParts = [];
        if (self::hasColumn('conversions', 'modified_date')) {
            $sortParts[] = 'cv.modified_date';
        }
        if (self::hasColumn('conversions', 'sale_date')) {
            $sortParts[] = 'cv.sale_date';
        }
        $sortParts[] = 'c.created_at';
        $sortExpr = 'COALESCE(' . implode(', ', array_unique($sortParts)) . ')';

        $eventTypeExpr = self::hasColumn('conversions', 'event_type')
            ? 'cv.event_type'
            : "'—'";
        $commissionExpr = self::hasColumn('conversions', 'commission_id')
            ? 'cv.commission_id'
            : 'NULL';
        $externalEventExpr = self::hasColumn('conversions', 'event_id')
            ? 'cv.event_id'
            : 'NULL';
        $advertiserExpr = self::hasColumn('conversions', 'advertiser_id')
            ? 'cv.advertiser_id'
            : 'NULL';
        $revenueExpr = self::hasColumn('conversions', 'revenue')
            ? 'cv.revenue'
            : '0';
        $currencyExpr = self::hasColumn('conversions', 'currency')
            ? "COALESCE(cv.currency, 'EUR')"
            : "'EUR'";
        $saleAmountExpr = self::hasColumn('conversions', 'sales_amount')
            ? 'cv.sales_amount'
            : 'NULL';

        $searchExprs = [
            'c.click_id',
            'COALESCE(o.name, \'\')',
            $campaignExpr,
            'COALESCE(aa.affiliate_program, \'\')',
        ];
        if (self::hasColumn('conversions', 'commission_id')) {
            $searchExprs[] = 'COALESCE(cv.commission_id, \'\')';
        }
        if (self::hasColumn('conversions', 'event_id')) {
            $searchExprs[] = 'COALESCE(cv.event_id, \'\')';
        }
        if (self::hasColumn('conversions', 'advertiser_id')) {
            $searchExprs[] = 'COALESCE(cv.advertiser_id, \'\')';
        }
        $where = self::searchClause($search, $searchExprs, $params);

        $sql = "
            SELECT
                cv.id,
                c.click_id AS public_click_id,
                COALESCE(o.name, '(unknown)') AS offer_name,
                {$campaignExpr} AS campaign_name,
                COALESCE(aa.affiliate_program, '(unassigned)') AS affiliate_name,
                cv.status AS status,
                {$revenueExpr} AS revenue,
                {$sortExpr} AS sort_ts,
                {$sortExpr} AS created_at,
                1 AS is_read,
                {$eventTypeExpr} AS event_type,
                {$commissionExpr} AS commission_id,
                {$externalEventExpr} AS external_event_id,
                {$advertiserExpr} AS advertiser_external_id,
                {$currencyExpr} AS currency,
                {$saleAmountExpr} AS sales_amount
            FROM conversions cv
            INNER JOIN clicks c ON c.id = cv.click_id
            LEFT JOIN offers o ON o.id = c.offer_id
            LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
            WHERE 1=1 {$where}
            ORDER BY sort_ts DESC
            LIMIT :limit OFFSET :offset
        ";

        return self::normalizeRows(self::fetchAllPrepared($sql, $params));
    }

    private static function countFilteredFromNotifications(string $search = ''): int
    {
        $params = [];
        $campaignExpr = self::campaignNameFromClicksExpr(
            'c.campaign_id',
            self::hasColumn('notifications', 'campaign_id') ? 'n.campaign_id' : null,
            self::hasColumn('notifications', 'campaign_id')
                ? "NULLIF(TRIM(CAST(n.campaign_id AS CHAR)), '')"
                : "'—'"
        );

        $affiliateLookupExpr = self::hasTable('affiliate_accounts')
            ? "(SELECT MAX(aa1.affiliate_program) FROM affiliate_accounts aa1 WHERE aa1.id = o.affiliate_program_id OR aa1.id = n.affiliate_id)"
            : 'NULL';
        $clickExpr = "COALESCE(c.click_id, CAST(n.click_id AS CHAR))";
        $offerExpr = "COALESCE(o.name, NULLIF(TRIM(CAST(n.offer_id AS CHAR)), ''), '(unknown)')";
        $affiliateExpr = "COALESCE({$affiliateLookupExpr}, '(unassigned)')";

        $searchExprs = [
            $clickExpr,
            $offerExpr,
            $campaignExpr,
            $affiliateExpr,
        ];
        if (self::hasColumn('notifications', 'commission_id')) {
            $searchExprs[] = 'COALESCE(n.commission_id, \'\')';
        }
        if (self::hasColumn('notifications', 'external_event_id')) {
            $searchExprs[] = 'COALESCE(n.external_event_id, \'\')';
        } elseif (self::hasColumn('notifications', 'event_id')) {
            $searchExprs[] = 'COALESCE(CAST(n.event_id AS CHAR), \'\')';
        }
        if (self::hasColumn('notifications', 'advertiser_external_id')) {
            $searchExprs[] = 'COALESCE(n.advertiser_external_id, \'\')';
        }

        $where = self::searchClause($search, $searchExprs, $params);

        $sql = "
            SELECT COUNT(DISTINCT n.id) AS cnt
            FROM notifications n
            LEFT JOIN clicks c ON c.id = n.click_id OR c.click_id = n.click_id
            LEFT JOIN offers o ON o.id = n.offer_id OR o.name = n.offer_id
            WHERE 1=1 {$where}
        ";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fetchPageFromNotifications(int $perPage, int $offset, string $search = ''): array
    {
        $params = [
            ':limit' => $perPage,
            ':offset' => $offset,
        ];

        $revenueExpr = self::hasColumn('notifications', 'revenue')
            ? 'n.revenue'
            : (self::hasColumn('notifications', 'payout') ? 'n.payout' : '0');

        $sortParts = [];
        if (self::hasColumn('notifications', 'modified_date')) {
            $sortParts[] = 'n.modified_date';
        }
        if (self::hasColumn('notifications', 'sale_date')) {
            $sortParts[] = 'n.sale_date';
        }
        if (self::hasColumn('notifications', 'created_at')) {
            $sortParts[] = 'n.created_at';
        }
        if (self::hasColumn('notifications', 'click_created_at')) {
            $sortParts[] = 'n.click_created_at';
        }
        $sortParts[] = 'c.created_at';
        $sortExpr = 'COALESCE(' . implode(', ', array_unique($sortParts)) . ')';

        $campaignExpr = self::campaignNameFromClicksExpr(
            'c.campaign_id',
            self::hasColumn('notifications', 'campaign_id') ? 'n.campaign_id' : null,
            self::hasColumn('notifications', 'campaign_id')
                ? "NULLIF(TRIM(CAST(n.campaign_id AS CHAR)), '')"
                : "'—'"
        );

        $affiliateLookupExpr = self::hasTable('affiliate_accounts')
            ? "(SELECT MAX(aa1.affiliate_program) FROM affiliate_accounts aa1 WHERE aa1.id = o.affiliate_program_id OR aa1.id = n.affiliate_id)"
            : 'NULL';
        $clickExpr = "COALESCE(c.click_id, CAST(n.click_id AS CHAR))";
        $offerExpr = "COALESCE(o.name, NULLIF(TRIM(CAST(n.offer_id AS CHAR)), ''), '(unknown)')";
        $affiliateExpr = "COALESCE({$affiliateLookupExpr}, '(unassigned)')";

        $isReadExpr = self::hasColumn('notifications', 'is_read') ? 'n.is_read' : '1';
        $eventTypeExpr = self::hasColumn('notifications', 'event_type') ? 'n.event_type' : "'—'";
        $commissionExpr = self::hasColumn('notifications', 'commission_id') ? 'n.commission_id' : 'NULL';
        $externalEventExpr = self::hasColumn('notifications', 'external_event_id')
            ? 'n.external_event_id'
            : (self::hasColumn('notifications', 'event_id') ? 'CAST(n.event_id AS CHAR)' : 'NULL');
        $advertiserExpr = self::hasColumn('notifications', 'advertiser_external_id') ? 'n.advertiser_external_id' : 'NULL';
        $currencyExpr = self::hasColumn('notifications', 'currency')
            ? "COALESCE(n.currency, 'EUR')"
            : "'EUR'";
        $saleAmountExpr = self::hasColumn('notifications', 'sales_amount') ? 'n.sales_amount' : 'NULL';

        $searchExprs = [
            $clickExpr,
            $offerExpr,
            $campaignExpr,
            $affiliateExpr,
        ];
        if (self::hasColumn('notifications', 'commission_id')) {
            $searchExprs[] = 'COALESCE(n.commission_id, \'\')';
        }
        if (self::hasColumn('notifications', 'external_event_id')) {
            $searchExprs[] = 'COALESCE(n.external_event_id, \'\')';
        } elseif (self::hasColumn('notifications', 'event_id')) {
            $searchExprs[] = 'COALESCE(CAST(n.event_id AS CHAR), \'\')';
        }
        if (self::hasColumn('notifications', 'advertiser_external_id')) {
            $searchExprs[] = 'COALESCE(n.advertiser_external_id, \'\')';
        }

        $where = self::searchClause($search, $searchExprs, $params);

        $sql = "
            SELECT
                n.id,
                {$clickExpr} AS public_click_id,
                {$offerExpr} AS offer_name,
                {$campaignExpr} AS campaign_name,
                {$affiliateExpr} AS affiliate_name,
                n.status AS status,
                {$revenueExpr} AS revenue,
                {$sortExpr} AS sort_ts,
                {$sortExpr} AS created_at,
                {$isReadExpr} AS is_read,
                {$eventTypeExpr} AS event_type,
                {$commissionExpr} AS commission_id,
                {$externalEventExpr} AS external_event_id,
                {$advertiserExpr} AS advertiser_external_id,
                {$currencyExpr} AS currency,
                {$saleAmountExpr} AS sales_amount
            FROM notifications n
            LEFT JOIN clicks c ON c.id = n.click_id OR c.click_id = n.click_id
            LEFT JOIN offers o ON o.id = n.offer_id OR o.name = n.offer_id
            WHERE 1=1 {$where}
            ORDER BY sort_ts DESC
            LIMIT :limit OFFSET :offset
        ";

        return self::normalizeRows(self::fetchAllPrepared($sql, $params));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $row['status'] = self::normalizeStatus($row['status'] ?? null);
            $row['event_type'] = self::normalizeEventType($row['event_type'] ?? null);
            $row['is_read'] = (int) ((int) ($row['is_read'] ?? 1) > 0);
            $row['revenue'] = (float) ($row['revenue'] ?? 0);
            $row['currency'] = self::normalizeCurrency($row['currency'] ?? 'EUR');
            $row['campaign_name'] = trim((string) ($row['campaign_name'] ?? '')) !== ''
                ? (string) $row['campaign_name']
                : '—';
            $row['event_type'] = trim((string) $row['event_type']) !== ''
                ? (string) $row['event_type']
                : '—';
            $row['sort_ts'] = $row['sort_ts'] ?? ($row['created_at'] ?? null);

            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $raw
     */
    private static function normalizeStatus($raw): string
    {
        if ($raw === null) {
            return 'unknown';
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return 'unknown';
        }

        if (ctype_digit($value)) {
            $n = (int) $value;
            return match ($n) {
                1 => 'open',
                2 => 'confirmed',
                3 => 'rejected',
                4 => 'paid',
                default => 'unknown',
            };
        }

        $value = strtolower($value);
        return match ($value) {
            'open', 'confirmed', 'rejected', 'paid', 'delayed', 'unknown' => $value,
            default => $value,
        };
    }

    /**
     * @param mixed $raw
     */
    private static function normalizeEventType($raw): string
    {
        if ($raw === null) {
            return '—';
        }

        $value = trim((string) $raw);
        if ($value === '' || $value === '0') {
            return '—';
        }

        if (ctype_digit($value)) {
            $n = (int) $value;
            return match ($n) {
                1 => 'new',
                2 => 'update',
                default => '—',
            };
        }

        return strtolower($value);
    }

    /**
     * @param mixed $raw
     */
    private static function normalizeCurrency($raw): string
    {
        $value = strtoupper(trim((string) $raw));
        return $value !== '' ? $value : 'EUR';
    }
}
