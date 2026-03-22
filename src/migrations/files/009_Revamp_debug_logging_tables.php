<?php

/**
 * Truncates legacy log data and reshapes redirect_logs, postback_logs, traffic_source_logs
 * for flexible debug statuses and longer query capture.
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
        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach (['redirect_logs', 'postback_logs', 'traffic_source_logs'] as $t) {
            if ($tableExists($db, $t)) {
                $db->exec("TRUNCATE TABLE `{$t}`");
            }
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=1');

        if ($tableExists($db, 'redirect_logs')) {
            $db->exec("
                ALTER TABLE redirect_logs
                MODIFY COLUMN status VARCHAR(80) NOT NULL DEFAULT '',
                MODIFY COLUMN reason VARCHAR(512) NULL,
                MODIFY COLUMN campaign_id VARCHAR(64) NOT NULL DEFAULT '',
                MODIFY COLUMN raw_query TEXT NOT NULL,
                MODIFY COLUMN ip VARBINARY(16) NOT NULL
            ");
            if (!$columnExists($db, 'redirect_logs', 'context')) {
                $db->exec('ALTER TABLE redirect_logs ADD COLUMN context LONGTEXT NULL AFTER ip');
            }
            if (!$columnExists($db, 'redirect_logs', 'level')) {
                $db->exec("ALTER TABLE redirect_logs ADD COLUMN level VARCHAR(16) NOT NULL DEFAULT 'info' AFTER context");
            }
            try {
                $db->exec('CREATE INDEX idx_redirect_logs_status ON redirect_logs (status)');
            } catch (Throwable $e) {
            }
            try {
                $db->exec('CREATE INDEX idx_redirect_logs_created ON redirect_logs (created_at)');
            } catch (Throwable $e) {
            }
        } else {
            $db->exec("
                CREATE TABLE redirect_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    status VARCHAR(80) NOT NULL DEFAULT '',
                    reason VARCHAR(512) NULL,
                    campaign_id VARCHAR(64) NOT NULL DEFAULT '',
                    raw_query TEXT NOT NULL,
                    ip VARBINARY(16) NOT NULL,
                    context LONGTEXT NULL,
                    level VARCHAR(16) NOT NULL DEFAULT 'info',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_redirect_logs_status (status),
                    KEY idx_redirect_logs_created (created_at),
                    KEY idx_redirect_logs_campaign (campaign_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($tableExists($db, 'postback_logs')) {
            $db->exec("
                ALTER TABLE postback_logs
                MODIFY COLUMN status VARCHAR(80) NOT NULL DEFAULT '',
                MODIFY COLUMN reason VARCHAR(512) NULL,
                MODIFY COLUMN click_id VARCHAR(64) NULL,
                MODIFY COLUMN raw_query TEXT NOT NULL,
                MODIFY COLUMN ip VARBINARY(16) NOT NULL
            ");
            foreach (['idx_postback_logs_status' => 'status', 'idx_postback_logs_created' => 'created_at'] as $idx => $col) {
                try {
                    $db->exec("CREATE INDEX {$idx} ON postback_logs ({$col})");
                } catch (Throwable $e) {
                }
            }
        } else {
            $db->exec("
                CREATE TABLE postback_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    status VARCHAR(80) NOT NULL DEFAULT '',
                    reason VARCHAR(512) NULL,
                    click_id VARCHAR(64) NULL,
                    raw_query TEXT NOT NULL,
                    ip VARBINARY(16) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_postback_logs_status (status),
                    KEY idx_postback_logs_created (created_at),
                    KEY idx_postback_logs_click (click_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($tableExists($db, 'traffic_source_logs')) {
            if (!$columnExists($db, 'traffic_source_logs', 'internal_campaign_id')) {
                $db->exec('ALTER TABLE traffic_source_logs ADD COLUMN internal_campaign_id BIGINT UNSIGNED NULL AFTER traffic_source_id');
            }
            $db->exec("
                ALTER TABLE traffic_source_logs
                MODIFY COLUMN campaign_id VARCHAR(64) NOT NULL DEFAULT '',
                MODIFY COLUMN response TEXT NOT NULL,
                MODIFY COLUMN http_code SMALLINT UNSIGNED NOT NULL
            ");
        }

        // MySQL < 8.0.13 may not support CREATE INDEX IF NOT EXISTS — ignore failures
        if ($tableExists($db, 'traffic_source_logs') && $columnExists($db, 'traffic_source_logs', 'internal_campaign_id')) {
            try {
                $db->exec('CREATE INDEX idx_traffic_source_logs_internal ON traffic_source_logs (internal_campaign_id)');
            } catch (Throwable $e) {
            }
        }
    },

    'down' => function (PDO $db): void {
        // No safe restore of truncated data; leave structure as-is.
    },
];
