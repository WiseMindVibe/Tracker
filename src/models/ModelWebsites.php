<?php

require_once __DIR__ . "/ModelBase.php";

class ModelWebsites extends ModelBase
{
    protected static string $table = "websites";

    protected static array $columns = [
        "t.id",
        "t.domain",
        "t.country",
        "COUNT(t.id) OVER() AS total_count"

    ];

    protected static array $joins = [
    ];

    /** Keys must match ControllerWebsites $config keys. */
    protected static array $filterMap = [
        'id' => ['column' => 't.id', 'type' => 'exact'],
        'website' => ['column' => 't.domain', 'type' => 'like'],
        'country' => ['column' => 't.country', 'type' => 'like'],
    ];

    /* =======================================================
       EXTRA METHODS USED BY CONTROLLER
       ======================================================= */

    public static function create($domain, $country)
    {
        $db = db();

        $stmt = $db->prepare("INSERT INTO websites (domain, country, created_at, updated_at)
        VALUES
        (:domain, :country, NOW(), NOW())");

        $stmt->execute(
            [
                ':domain' => $domain,
                ':country' => $country
            ]
        );

        return true;
    }

    public static function view($id)
    {
        $db = db();

        $stmt = $db->prepare("SELECT domain, country
        FROM websites
        WHERE id = :id");

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}