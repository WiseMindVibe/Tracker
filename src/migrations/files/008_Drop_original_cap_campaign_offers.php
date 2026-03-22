<?php

/**
 * Removes original_cap from campaign_offers if present (replaced by using abs(cap) only).
 */

return [
    'up' => function (PDO $db): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if (!$schema) {
            throw new RuntimeException('No database selected');
        }
        $qSchema = $db->quote((string) $schema);

        $exists = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaign_offers'
              AND COLUMN_NAME = 'original_cap'
        ")->fetchColumn();

        if ($exists) {
            $db->exec('ALTER TABLE campaign_offers DROP COLUMN original_cap');
        }
    },

    'down' => function (PDO $db): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if (!$schema) {
            return;
        }
        $qSchema = $db->quote((string) $schema);

        $table = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaign_offers'
        ")->fetchColumn();
        if (!$table) {
            return;
        }

        $hasCol = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaign_offers'
              AND COLUMN_NAME = 'original_cap'
        ")->fetchColumn();
        if (!$hasCol) {
            $db->exec('ALTER TABLE campaign_offers ADD COLUMN original_cap INT NULL DEFAULT NULL');
        }
    },
];
