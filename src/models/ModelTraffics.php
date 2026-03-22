<?php

require_once __DIR__ . "/ModelBase.php";

class ModelTraffics extends ModelBase
{
    protected static string $table = "traffic_sources";

    protected static array $columns = [
        "t.id",
        "t.name",
        "t.api_key",
        "COUNT(t.id) OVER() AS total_count"

    ];

    protected static array $joins = [
    ];

    protected static array $filterMap = [
        'id' => ['column' => 't.id', 'type' => 'exact'],
        'name' => ['column' => 't.name', 'type' => 'like'],
        'api_key' => ['column' => 't.api_key', 'type' => 'like'],
    ];

    /* =======================================================
       EXTRA METHODS USED BY CONTROLLER
       ======================================================= */

    public static function create($name, $api)
    {
        $db = db();

        $stmt = $db->prepare("INSERT INTO traffic_sources (name, api_key, created_at, updated_at)
        VALUES
        (:name, :api, NOW(), NOW())");

        $stmt->execute([
            ':name' => $name,
            ':api' => $api
        ]);

        return true;
    }

    public static function view($id)
    {
        $db = db();

        $stmt = $db->prepare("SELECT name, api_key
        FROM traffic_sources
        WHERE id = :id");

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}