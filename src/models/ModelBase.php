<?php

class ModelBase
{
    protected static string $table;
    protected static array $joins = [];
    protected static array $columns = [];
    protected static array $filterMap = [];

    public static function getAll($filters, $sort, $direction, $limit, $offset)
    {
        $sql = "SELECT " . implode(", ", static::$columns) .
            " FROM " . static::$table . " t ";

        foreach (static::$joins as $join) {
            $sql .= " $join ";
        }

        $sql .= " WHERE 1=1 ";


        $params = [];

        foreach (static::$filterMap as $key => $config) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $param = ":" . $key;

                if ($config['type'] === 'like') {
                    $sql .= " AND {$config['column']} LIKE $param";
                    $params[$param] = "%" . $filters[$key] . "%";
                } else {
                    $sql .= " AND {$config['column']} = $param";
                    $params[$param] = $filters[$key];
                }
            }
        }

        $sql .= " ORDER BY $sort $direction LIMIT $limit OFFSET $offset";

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;

        if (isset($rows)) {
            $total = $rows[0]['total_count'];

            foreach ($rows as &$row) {
                unset($row['total_count']);
            }
        }

        return [$total, $rows];
    }

}