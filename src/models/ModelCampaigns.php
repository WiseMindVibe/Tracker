<?php

require_once __DIR__ . "/ModelBase.php";

class ModelCampaigns extends ModelBase
{
    protected static string $table = "campaigns";

    protected static array $columns = [
        "t.id",
        "t.tester",
        "t.name AS campaign",

        "CONCAT(
            COALESCE(co.total_views, 0),
            ' / ',
            COALESCE(co.total_cap, 0),
            ' - ',
            COALESCE(
                ROUND(co.total_views / NULLIF(ABS(COALESCE(co.total_cap, 0)), 0) * 100, 2),
                0
            ),
            '%'
        ) AS views",

        "COALESCE(
            ROUND(co.total_views / NULLIF(ABS(COALESCE(co.total_cap, 0)), 0) * 100, 2),
            0
        ) AS percentage",

        "cei.external_campaign_id",
        "t.country",
        "ts.name AS traffic",
        "t.uuid",
        "t.created_at",
        "t.updated_at",
        "COUNT(t.id) OVER() AS total_count",
    ];

    protected static array $joins = [
        "LEFT JOIN traffic_sources ts ON ts.id = t.traffic_source_id",
        "LEFT JOIN (
            SELECT
                campaign_id,
                SUM(current_views) AS total_views,
                SUM(cap) AS total_cap
            FROM campaign_offers
            GROUP BY campaign_id
        ) co ON co.campaign_id = t.id",
        "LEFT JOIN (
            SELECT campaign_id,
                GROUP_CONCAT(external_campaign_id ORDER BY external_campaign_id SEPARATOR ' ') AS external_campaign_id
            FROM campaign_external_ids
            GROUP BY campaign_id
        ) cei ON cei.campaign_id = t.id",
    ];

    /** Keys must match Controller* $config keys (ControllerBase passes $filters[$configKey]). */
    protected static array $filterMap = [
        'id' => ['column' => 't.id', 'type' => 'exact'],
        'tester' => ['column' => 't.tester', 'type' => 'exact'],
        'campaign' => ['column' => 't.name', 'type' => 'like'],
        'external_campaign_id' => ['column' => 'cei.external_campaign_id', 'type' => 'like'],
        'country' => ['column' => 't.country', 'type' => 'like'],
        'uuid' => ['column' => 't.uuid', 'type' => 'exact'],
        'traffic_source' => ['column' => 'ts.name', 'type' => 'like'],
        'created_time' => ['column' => 't.created_at', 'type' => 'like'],
        'updated_time' => ['column' => 't.updated_at', 'type' => 'like'],
    ];

    public static function GetTrafficsSources(): array
    {
        $stmt = db()->query('SELECT id, name FROM traffic_sources ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listOffersForSelect(): array
    {
        $stmt = db()->query('SELECT id, name FROM offers ORDER BY name ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<array{offer_id: int, cap: int}> $offerAssignments
     */
    public static function create(
        string $name,
        $tester,
        $traffic_source_id,
        string $country,
        string $uuid,
        array $external_campaigns = [],
        array $offerAssignments = []
    ): bool {
        $db = db();
        $tester = (int) ($tester ?? 0);

        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO campaigns
                (name, tester, traffic_source_id, country, uuid, created_at, updated_at)
                VALUES
                (:name, :tester, :traffic_source_id, :country, :uuid, NOW(), NOW())
            ");

            $stmt->execute([
                ':name' => $name,
                ':tester' => $tester,
                ':traffic_source_id' => $traffic_source_id,
                ':country' => strtoupper(trim($country)),
                ':uuid' => $uuid,
            ]);

            $createdCampaignId = (int) $db->lastInsertId();

            foreach ($external_campaigns as $external_campaign_id) {
                if ($external_campaign_id === null || trim((string) $external_campaign_id) === '') {
                    continue;
                }

                $stmt2 = $db->prepare("
                    INSERT INTO campaign_external_ids (campaign_id, external_campaign_id)
                    VALUES (:campaign_id, :external_campaign_id)
                ");
                $stmt2->execute([
                    ':campaign_id' => $createdCampaignId,
                    ':external_campaign_id' => trim((string) $external_campaign_id),
                ]);
            }

            $seen = [];
            foreach ($offerAssignments as $row) {
                $oid = (int) ($row['offer_id'] ?? 0);
                if ($oid < 1 || isset($seen[$oid])) {
                    continue;
                }
                $seen[$oid] = true;
                $cap = (int) ($row['cap'] ?? 0);

                $stmt3 = $db->prepare("
                    INSERT INTO campaign_offers (campaign_id, offer_id, cap, current_views)
                    VALUES (:campaign_id, :offer_id, :cap, 0)
                ");
                $stmt3->execute([
                    ':campaign_id' => $createdCampaignId,
                    ':offer_id' => $oid,
                    ':cap' => $cap,
                ]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function view(int $id): array
    {
        $db = db();

        $stmt = $db->prepare("
            SELECT
                c.id,
                c.tester,
                c.name AS campaign,
                c.country,
                c.traffic_source_id,
                ts.name AS traffic,
                c.uuid
            FROM campaigns c
            LEFT JOIN traffic_sources ts ON ts.id = c.traffic_source_id
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            return [];
        }

        $stmt = $db->prepare("
            SELECT external_campaign_id
            FROM campaign_external_ids
            WHERE campaign_id = :id
        ");
        $stmt->execute([':id' => $id]);
        $external_campaigns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'external_campaign_id');
        $campaign['external_campaign_id'] = $external_campaigns;

        $stmt = $db->prepare("
            SELECT co.offer_id, o.name AS offer_name, co.cap, co.current_views
            FROM campaign_offers co
            INNER JOIN offers o ON o.id = co.offer_id
            WHERE co.campaign_id = :cid
            ORDER BY o.name ASC
        ");
        $stmt->execute([':cid' => $id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $campaign['campaign_offers'] = [];
        foreach ($rows as $r) {
            $campaign['campaign_offers'][] = [
                'offer_id' => (int) $r['offer_id'],
                'name' => $r['offer_name'],
                'cap' => (int) $r['cap'],
                'current_views' => (int) $r['current_views'],
            ];
        }

        return $campaign;
    }

    public static function save(array $data): bool
    {
        $db = db();
        $db->beginTransaction();

        try {
            $id = (int) ($data['id'] ?? 0);
            if ($id < 1) {
                throw new RuntimeException('Missing campaign ID in save()');
            }

            $stmt = $db->prepare("
                UPDATE campaigns
                SET
                    name = :name,
                    tester = :tester,
                    country = :country,
                    traffic_source_id = :traffic_source_id,
                    uuid = :uuid,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $id,
                ':name' => $data['campaign'],
                ':tester' => (int) ($data['tester'] ?? 0),
                ':traffic_source_id' => $data['traffic_source_id'],
                ':country' => strtoupper(trim((string) ($data['country'] ?? ''))),
                ':uuid' => $data['uuid'],
            ]);

            $db->prepare('DELETE FROM campaign_external_ids WHERE campaign_id = :id')->execute([':id' => $id]);

            foreach ($data['external_campaign_id'] ?? [] as $external) {
                $external = trim((string) $external);
                if ($external === '') {
                    continue;
                }
                $stmt = $db->prepare("
                    INSERT INTO campaign_external_ids (campaign_id, external_campaign_id)
                    VALUES (:cid, :eid)
                ");
                $stmt->execute([':cid' => $id, ':eid' => $external]);
            }

            $incoming = $data['campaign_offers'] ?? [];
            if (!is_array($incoming)) {
                $incoming = [];
            }

            $keepIds = [];
            foreach ($incoming as $row) {
                $oid = (int) ($row['offer_id'] ?? 0);
                if ($oid < 1) {
                    continue;
                }
                $keepIds[$oid] = true;
                $cap = (int) ($row['cap'] ?? 0);

                $sel = $db->prepare("
                    SELECT current_views, cap FROM campaign_offers
                    WHERE campaign_id = :cid AND offer_id = :oid
                    LIMIT 1
                ");
                $sel->execute([':cid' => $id, ':oid' => $oid]);
                $existing = $sel->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $up = $db->prepare("
                        UPDATE campaign_offers
                        SET cap = :cap
                        WHERE campaign_id = :cid AND offer_id = :oid
                    ");
                    $up->execute([
                        ':cap' => $cap,
                        ':cid' => $id,
                        ':oid' => $oid,
                    ]);
                } else {
                    $ins = $db->prepare("
                        INSERT INTO campaign_offers (campaign_id, offer_id, cap, current_views)
                        VALUES (:cid, :oid, :cap, 0)
                    ");
                    $ins->execute([
                        ':cid' => $id,
                        ':oid' => $oid,
                        ':cap' => $cap,
                    ]);
                }
            }

            if ($keepIds === []) {
                $del = $db->prepare('DELETE FROM campaign_offers WHERE campaign_id = :cid');
                $del->execute([':cid' => $id]);
            } else {
                $ids = array_keys($keepIds);
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$id], $ids);
                $sql = "
                    DELETE FROM campaign_offers
                    WHERE campaign_id = ?
                      AND offer_id NOT IN ($ph)
                ";
                $db->prepare($sql)->execute($params);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Daily reset: zero all view counters on assignments.
     */
    public static function resetAllCampaignOfferViews(): void
    {
        db()->exec('UPDATE campaign_offers SET current_views = 0');
    }

    /**
     * For tester campaigns only: use yesterday's click cost vs paid/open/confirmed revenue per offer.
     * ROI &lt; 200% → cap = -abs(cap) using the row’s current cap magnitude. If cap was already negative, it stays -abs(cap).
     * ROI ≥ 200% and cap &lt; 0 → cap = abs(cap) (turn traffic back on at the same magnitude you paused with).
     */
    public static function applyTesterCapsFromYesterday(): void
    {
        $db = db();
        $day = (new DateTimeImmutable('yesterday'))->format('Y-m-d');

        $sql = "
            SELECT
                cam.id AS campaign_internal_id,
                cl.offer_id,
                COALESCE(SUM(cl.cost), 0) AS spent,
                COALESCE(SUM(CASE WHEN cv.status IN ('open','confirmed','paid','delayed') THEN cv.payout ELSE 0 END), 0) AS revenue
            FROM clicks cl
            LEFT JOIN conversions cv ON cv.click_internal_id = cl.id
            INNER JOIN campaigns cam ON cam.uuid = cl.campaign_id OR cam.id = cl.campaign_id
            WHERE cam.tester = 1
              AND cl.offer_id IS NOT NULL
              AND DATE(cl.created_at) = :day
            GROUP BY cam.id, cl.offer_id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':day' => $day]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sel = $db->prepare("
            SELECT cap FROM campaign_offers
            WHERE campaign_id = :cid AND offer_id = :oid
            LIMIT 1
        ");

        $up = $db->prepare('UPDATE campaign_offers SET cap = :cap WHERE campaign_id = :cid AND offer_id = :oid');

        foreach ($rows as $r) {
            $cid = (int) ($r['campaign_internal_id'] ?? 0);
            $oid = (int) ($r['offer_id'] ?? 0);
            if ($cid < 1 || $oid < 1) {
                continue;
            }

            $spent = (float) $r['spent'];
            $revenue = (float) $r['revenue'];
            if ($spent <= 0) {
                continue;
            }

            $roi = ($revenue / $spent) * 100.0;

            $sel->execute([':cid' => $cid, ':oid' => $oid]);
            $co = $sel->fetch(PDO::FETCH_ASSOC);
            if (!$co) {
                continue;
            }

            $capNow = (int) $co['cap'];
            $mag = abs($capNow);
            if ($mag <= 0) {
                continue;
            }

            if ($roi < 200) {
                $up->execute([':cap' => -$mag, ':cid' => $cid, ':oid' => $oid]);
            } elseif ($capNow < 0) {
                $up->execute([':cap' => $mag, ':cid' => $cid, ':oid' => $oid]);
            }
        }
    }

    /**
     * Campaigns that still have at least one assignment with cap &gt; current_views and cap &gt; 0 get a traffic-source "start" call.
     *
     * @return list<array<string, mixed>> log lines for CLI output
     */
    public static function activateHealthyCampaignsTraffic(): array
    {
        $db = db();
        $log = [];

        $q = $db->query("
            SELECT DISTINCT c.id, c.uuid, c.traffic_source_id
            FROM campaigns c
            INNER JOIN campaign_external_ids cei ON cei.campaign_id = c.id
            WHERE c.traffic_source_id IS NOT NULL
        ");
        $campaigns = $q->fetchAll(PDO::FETCH_ASSOC);

        foreach ($campaigns as $c) {
            $cid = (int) $c['id'];
            $tsId = (int) $c['traffic_source_id'];

            $chk = $db->prepare("
                SELECT COUNT(*) FROM campaign_offers
                WHERE campaign_id = ?
                  AND cap > 0
                  AND current_views < cap
            ");
            $chk->execute([$cid]);
            if ((int) $chk->fetchColumn() < 1) {
                continue;
            }

            $stmt = $db->prepare('SELECT external_campaign_id FROM campaign_external_ids WHERE campaign_id = :id');
            $stmt->execute([':id' => $cid]);
            $extAccum = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $extAccum = array_merge($extAccum, normalizeExternalIds($row['external_campaign_id'] ?? ''));
            }
            $extAccum = array_values(array_unique($extAccum));
            if ($extAccum === []) {
                continue;
            }

            $res = startTrafficSourceCampaign($tsId, $extAccum);
            $log[] = [
                'campaign_id' => $cid,
                'action' => 'start',
                'success' => $res['success'] ?? false,
                'detail' => $res,
            ];
        }

        return $log;
    }
}
