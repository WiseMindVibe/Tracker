<?php

class ModelNotifications
{
    private const ALLOWED_PER_PAGE = [25, 100, 500];

    private static function baseFromClause(): string
    {
        return "
            FROM notifications n
            JOIN clicks c ON c.id = n.click_id
            JOIN offers o ON o.id = n.offer_id
            JOIN affiliate_accounts aa ON aa.id = n.affiliate_id
        ";
    }

    /**
     * One campaign name per click (campaign_id may match uuid or numeric id).
     */
    private static function campaignNameExpr(): string
    {
        return "(
            SELECT COALESCE(MAX(cam.name), '—')
            FROM campaigns cam
            WHERE cam.uuid = c.campaign_id OR cam.id = c.campaign_id
        )";
    }

    private static function searchClause(string $search, array &$params): string
    {
        $search = trim($search);
        if ($search === '') {
            return '';
        }
        $params[':q'] = '%' . $search . '%';
        $camp = self::campaignNameExpr();

        return " AND (
            c.click_id LIKE :q
            OR o.name LIKE :q
            OR {$camp} LIKE :q
            OR aa.affiliate_program LIKE :q
            OR n.commission_id LIKE :q
            OR n.external_event_id LIKE :q
            OR n.advertiser_external_id LIKE :q
        )";
    }

    public static function allowedPerPage(): array
    {
        return self::ALLOWED_PER_PAGE;
    }

    public static function normalizePerPage(int $per): int
    {
        return in_array($per, self::ALLOWED_PER_PAGE, true) ? $per : 25;
    }

    /**
     * Mark all notifications read when the user opens this area.
     */
    public static function markAllRead(): void
    {
        try {
            db()->prepare("UPDATE notifications
                SET is_read = 1,
                read_at = NOW()
                WHERE is_read = 0
            ")->execute();
        } catch (Throwable $e) {
        }
    }

    public static function countFiltered(string $search = ''): int
    {
        $params = [];
        $where = self::searchClause($search, $params);
        $sql = 'SELECT COUNT(DISTINCT n.id) AS cnt ' . self::baseFromClause() . ' WHERE 1=1' . $where;
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchPage(int $page, int $perPage, string $search = ''): array
    {
        $perPage = self::normalizePerPage($perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $params = [];
        $where = self::searchClause($search, $params);
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $camp = self::campaignNameExpr();
        $sql = "
            SELECT
                n.id,
                c.click_id AS public_click_id,
                o.name AS offer_name,
                {$camp} AS campaign_name,
                COALESCE(aa.affiliate_program, '(unassigned)') AS affiliate_name,
                n.status,
                n.payout AS revenue,
                COALESCE(n.created_at, n.click_created_at) AS sort_ts,
                n.created_at,
                n.is_read,
                n.event_type,
                n.commission_id,
                n.external_event_id,
                COALESCE(n.currency, 'EUR') AS currency,
                n.sales_amount
            " . self::baseFromClause() . "
            WHERE 1=1
            " . $where . "
            ORDER BY sort_ts DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = db()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':limit' || $k === ':offset') {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v);
            }
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
