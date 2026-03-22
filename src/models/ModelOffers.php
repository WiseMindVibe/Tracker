<?php

require_once __DIR__ . "/ModelBase.php";

class ModelOffers extends ModelBase
{
    protected static string $table = "offers";

    protected static array $columns = [
        "t.id",
        "t.name",
        "t.country",
        "t.affiliate_link",
        "t.created_at",
        "t.updated_at",

        "aa.affiliate_program",
        "w.domain",

        "COALESCE(oa.url_count,0) AS url_count",
        "COUNT(t.id) OVER() AS total_count"
    ];

    protected static array $joins = [
        "LEFT JOIN affiliate_accounts aa ON aa.id = t.affiliate_program_id",
        "LEFT JOIN websites w ON w.id = t.website_id",
        "LEFT JOIN (
            SELECT offer_id, COUNT(*) AS url_count
            FROM offers_articles
            GROUP BY offer_id
        ) oa ON oa.offer_id = t.id"
    ];

    /** Keys must match ControllerOffers $config keys (ControllerBase passes $filters[$configKey]). */
    protected static array $filterMap = [
        'id' => ['column' => 't.id', 'type' => 'exact'],
        'name' => ['column' => 't.name', 'type' => 'like'],
        'affiliate_program' => ['column' => 'aa.affiliate_program', 'type' => 'like'],
        'country' => ['column' => 't.country', 'type' => 'like'],
        'affiliate_link' => ['column' => 't.affiliate_link', 'type' => 'like'],
        'website' => ['column' => 'w.domain', 'type' => 'like'],
        'articles' => ['column' => 'COALESCE(oa.url_count,0)', 'type' => 'exact'],
        'created_time' => ['column' => 't.created_at', 'type' => 'like'],
        'updated_time' => ['column' => 't.updated_at', 'type' => 'like'],
    ];


    /* =======================================================
       EXTRA METHODS USED BY CONTROLLER
       ======================================================= */
    /*
    public static function GetActiveAffiliatesSelected($id)
    {
        $db = db();

        try {
            $db->beginTransaction();

            $sql = "SELECT id, affiliate_program
            FROM affiliate_accounts
            WHERE active = 1 ";

            $params = [];
            if (isset($id)) {
                $sql .= " AND id = :id ";
                $params['id'] = $id;
            }

            $sql .= " ORDER BY id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    */
    public static function GetActiveAffiliates()
    {
        $stmt = db()->query("SELECT id, affiliate_program
            FROM affiliate_accounts
            WHERE active = 1
            ORDER BY id ASC");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function GetWebsites()
    {
        $stmt = db()->query("SELECT id, domain
        FROM websites
        ORDER BY domain ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function Create(
        $name,
        $affiliateId,
        $country,
        $affiliate_link,
        $websiteId,
        $articles = []
    ) {

        $db = db();

        try {

            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO offers 
            (name, affiliate_program_id, country, affiliate_link, website_id, created_at, updated_at)
            VALUES
            (:name, :affiliate, :country, :affiliate_link, :website, NOW(), NOW())
            ");

            $stmt->execute([
                ':name' => $name,
                ':affiliate' => $affiliateId,
                ':country' => $country,
                ':affiliate_link' => $affiliate_link,
                ':website' => $websiteId
            ]);

            $CreatedOfferId = $db->lastInsertId();

            foreach ($articles as $url) {
                $stmt2 = $db->prepare("INSERT INTO offers_articles (offer_id, article_url)
                    VALUES (:offer_id, :url)
                    ");
                $stmt2->execute([
                    ':offer_id' => $CreatedOfferId,
                    ':url' => $url
                ]);
            }

            $db->commit();

            return true;
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function view(int $id)
    {
        $db = db();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT
            o.id,
        o.name,
        aa.affiliate_program,
        o.country,
        o.affiliate_link,
        w.domain
        FROM offers o
        LEFT JOIN affiliate_accounts aa ON aa.id = o.affiliate_program_id
        LEFT JOIN websites w ON w.id = o.website_id
        WHERE o.id = :id
        ");

            $stmt->execute([':id' => $id]);

            $offer = $stmt->fetch(pdo::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT
        article_url
        FROM offers_articles
        WHERE offer_id = :id
        ");

            $stmt->execute([':id' => $id]);

            $articles = array_column(
                $stmt->fetchAll(PDO::FETCH_ASSOC),
                'article_url'
            );

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        $offer['articles'] = $articles;

        return $offer;
    }

    public static function save(array $data)
    {
        $stmt = db()->prepare("UPDATE offers
                            SET name = :name,
                            affiliate_program_id = :affiliate_program,
                            country = :country,
                            affiliate_link = :link,
                            website_id = :website,
                            updated_at = NOW()
                            WHERE id = :id
                            ");

        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':affiliate_program' => $data['affiliate_program_id'],
            ':country' => strtoupper($data['country']),
            ':link' => $data['affiliate_link'],
            ':website' => $data['website_id']
        ]);

        // reset articles
        db()->prepare("DELETE FROM offers_articles WHERE offer_id = :id")
            ->execute([':id' => $data['id']]);

        foreach ($data['articles'] ?? [] as $url) {
            db()->prepare("INSERT INTO offers_articles (offer_id, article_url)
                VALUES (:offer_id, :article_url)
            ")->execute([
                        ':offer_id' => $data['id'],
                        ':article_url' => $url
                    ]);
        }

        return ['success' => true];
    }
}