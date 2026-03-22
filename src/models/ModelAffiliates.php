<?php

require_once __DIR__ . "/ModelBase.php";

class ModelAffiliates extends ModelBase
{
    protected static string $table = "affiliate_accounts";

    protected static array $columns = [
        "t.id",
        "t.affiliate_program",
        "t.active",
        "COUNT(t.id) OVER() AS total_count"
    ];

    protected static array $joins = [
    ];

    protected static array $filterMap = [
        'id' => ['column' => 't.id', 'type' => 'exact'],
        'affiliate_program' => ['column' => 't.affiliate_program', 'type' => 'like'],
        'active' => ['column' => 't.active', 'type' => 'exact'],
    ];

    public static function create($name, $active)
    {
        //Build Create new affiliate
        $db = db();

        try {
            $db->beginTransaction();

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
