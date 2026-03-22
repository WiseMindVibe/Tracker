<?php

/**
 * Store S2S ADVERTISER_ID on commission_events and notifications.
 */

$columnExists = static function (PDO $db, string $table, string $column): bool {
    $schema = $db->query('SELECT DATABASE()')->fetchColumn();
    $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ' . $db->quote((string) $schema) . '
          AND TABLE_NAME = ' . $db->quote($table) . '
          AND COLUMN_NAME = ' . $db->quote($column);

    return (int) $db->query($sql)->fetchColumn() > 0;
};

$tableExists = static function (PDO $db, string $table): bool {
    $schema = $db->query('SELECT DATABASE()')->fetchColumn();
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ' . $db->quote((string) $schema) . '
          AND TABLE_NAME = ' . $db->quote($table);

    return (int) $db->query($sql)->fetchColumn() > 0;
};

return [
    'up' => function (PDO $db) use ($columnExists, $tableExists): void {
        if ($tableExists($db, 'commission_events') && !$columnExists($db, 'commission_events', 'advertiser_external_id')) {
            $db->exec('
                ALTER TABLE commission_events
                ADD COLUMN advertiser_external_id VARCHAR(128) NULL
                    COMMENT \'S2S ADVERTISER_ID from postback\'
                    AFTER external_event_id
            ');
        }
        if ($tableExists($db, 'notifications') && !$columnExists($db, 'notifications', 'advertiser_external_id')) {
            $db->exec('
                ALTER TABLE notifications
                ADD COLUMN advertiser_external_id VARCHAR(128) NULL
                    COMMENT \'S2S ADVERTISER_ID from postback\'
                    AFTER external_event_id
            ');
        }
    },

    'down' => function (PDO $db) use ($columnExists, $tableExists): void {
        if ($tableExists($db, 'commission_events') && $columnExists($db, 'commission_events', 'advertiser_external_id')) {
            $db->exec('ALTER TABLE commission_events DROP COLUMN advertiser_external_id');
        }
        if ($tableExists($db, 'notifications') && $columnExists($db, 'notifications', 'advertiser_external_id')) {
            $db->exec('ALTER TABLE notifications DROP COLUMN advertiser_external_id');
        }
    },
];
