<?php

/**
 * Per-commission postback rows (YieldKit-style), conversions.delayed, notifications extras.
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
        if (!$tableExists($db, 'commission_events')) {
            $db->exec("
                CREATE TABLE commission_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    click_internal_id BIGINT NOT NULL,
                    dedupe_key VARCHAR(192) NOT NULL COMMENT 'commission_id or evt:event_id or legacy',
                    commission_id VARCHAR(128) NULL,
                    external_event_id VARCHAR(128) NULL,
                    event_type VARCHAR(16) NULL,
                    commission_eur DECIMAL(14,5) NOT NULL DEFAULT 0.00000,
                    sales_amount_eur DECIMAL(14,5) NULL,
                    state VARCHAR(32) NOT NULL DEFAULT '',
                    sales_date DATETIME NULL,
                    modified_date DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_commission_events_click_dedupe (click_internal_id, dedupe_key),
                    KEY idx_commission_events_click (click_internal_id),
                    KEY idx_commission_events_modified (modified_date),
                    CONSTRAINT fk_commission_events_click
                        FOREIGN KEY (click_internal_id) REFERENCES clicks(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if ($tableExists($db, 'conversions')) {
            $db->exec("
                ALTER TABLE conversions
                MODIFY COLUMN status ENUM('open','confirmed','paid','rejected','delayed') NOT NULL
            ");
        }

        if ($tableExists($db, 'notifications')) {
            $notifCols = [
                'event_type' => 'VARCHAR(16) NULL',
                'commission_id' => 'VARCHAR(128) NULL',
                'external_event_id' => 'VARCHAR(128) NULL',
                'currency' => "VARCHAR(8) NOT NULL DEFAULT 'EUR'",
                'sales_amount' => 'DECIMAL(14,5) NULL',
                'is_read' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'read_at' => 'DATETIME NULL',
                'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ];
            foreach ($notifCols as $col => $def) {
                if (!$columnExists($db, 'notifications', $col)) {
                    $db->exec("ALTER TABLE notifications ADD COLUMN {$col} {$def}");
                }
            }
        } else {
            $db->exec("
                CREATE TABLE notifications (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    click_id BIGINT NOT NULL,
                    offer_id INT NOT NULL,
                    affiliate_id INT NOT NULL,
                    status VARCHAR(32) NOT NULL,
                    payout DECIMAL(14,5) NOT NULL,
                    click_created_at DATETIME NOT NULL,
                    click_updated_at DATETIME NOT NULL,
                    event_type VARCHAR(16) NULL,
                    commission_id VARCHAR(128) NULL,
                    external_event_id VARCHAR(128) NULL,
                    currency VARCHAR(8) NOT NULL DEFAULT 'EUR',
                    sales_amount DECIMAL(14,5) NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    read_at DATETIME NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_notifications_click (click_id),
                    KEY idx_notifications_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    },

    'down' => function (PDO $db) use ($tableExists): void {
        if ($tableExists($db, 'commission_events')) {
            $db->exec('DROP TABLE commission_events');
        }
    },
];
