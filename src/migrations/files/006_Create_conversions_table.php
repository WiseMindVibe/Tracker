<?php

/**
 * Adds `conversions` (1 row per click, current postback state).
 * Migrates status, payout, and optional event_id off `clicks`, then drops those columns.
 *
 * Safe if `clicks.status` is already removed (skips data copy / column drops).
 */

return [
    'up' => function (PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS conversions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                click_internal_id BIGINT NOT NULL COMMENT 'clicks.id',
                status ENUM('open','confirmed','paid','rejected') NOT NULL,
                payout DECIMAL(10,5) NOT NULL DEFAULT 0.00000,
                external_event_id VARCHAR(64) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_conversions_click_internal (click_internal_id),
                KEY idx_conversions_status (status),
                KEY idx_conversions_updated (updated_at),
                CONSTRAINT fk_conversions_click
                    FOREIGN KEY (click_internal_id) REFERENCES clicks(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        $hasStatus = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'status'
        ")->fetchColumn();

        if (!$hasStatus) {
            return;
        }

        $hasEventId = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'event_id'
        ")->fetchColumn();

        $eventExpr = $hasEventId
            ? "NULLIF(TRIM(c.event_id), '')"
            : 'NULL';

        $db->exec("
            INSERT INTO conversions (click_internal_id, status, payout, external_event_id, created_at, updated_at)
            SELECT
                c.id,
                LOWER(TRIM(c.status)),
                c.payout,
                $eventExpr,
                c.updated_at,
                c.updated_at
            FROM clicks c
            WHERE c.status IS NOT NULL
              AND TRIM(c.status) <> ''
              AND LOWER(TRIM(c.status)) IN ('open','confirmed','paid','rejected')
              AND NOT EXISTS (
                  SELECT 1 FROM conversions x WHERE x.click_internal_id = c.id
              )
        ");

        $db->exec('ALTER TABLE clicks DROP COLUMN status');

        $hasPayout = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'payout'
        ")->fetchColumn();
        if ($hasPayout) {
            $db->exec('ALTER TABLE clicks DROP COLUMN payout');
        }

        if ($hasEventId) {
            $db->exec('ALTER TABLE clicks DROP COLUMN event_id');
        }
    },

    'down' => function (PDO $db): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();

        $tableExists = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'conversions'
        ")->fetchColumn();

        if (!$tableExists) {
            return;
        }

        $hasStatus = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'status'
        ")->fetchColumn();

        if (!$hasStatus) {
            $db->exec("
                ALTER TABLE clicks
                ADD COLUMN status ENUM('open','confirmed','paid','rejected') DEFAULT NULL
            ");
        }

        $hasPayout = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'payout'
        ")->fetchColumn();

        if (!$hasPayout) {
            $db->exec("
                ALTER TABLE clicks
                ADD COLUMN payout DECIMAL(10,5) NOT NULL DEFAULT 0.00000
            ");
        }

        $hasEventId = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = " . $db->quote((string) $schema) . "
              AND TABLE_NAME = 'clicks'
              AND COLUMN_NAME = 'event_id'
        ")->fetchColumn();

        if (!$hasEventId) {
            $db->exec("
                ALTER TABLE clicks
                ADD COLUMN event_id VARCHAR(64) NOT NULL DEFAULT ''
            ");
        }

        $db->exec("
            UPDATE clicks c
            INNER JOIN conversions v ON v.click_internal_id = c.id
            SET
                c.status = v.status,
                c.payout = v.payout,
                c.event_id = COALESCE(v.external_event_id, '')
        ");

        $db->exec('DROP TABLE conversions');
    },
];
