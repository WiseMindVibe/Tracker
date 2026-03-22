<?php

/**
 * Ensures campaign_offers matches the app: campaign_id INT → campaigns.id, offer_id, cap, current_views, timestamps.
 * Renames legacy campaigns_offers → campaign_offers when present.
 * Does not convert campaign_id to UUID (tracking links still use campaigns.uuid in clicks/redirect).
 */

return [
    'up' => function (PDO $db): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if (!$schema) {
            throw new RuntimeException('No database selected');
        }
        $qSchema = $db->quote((string) $schema);

        $tableExists = static function (string $table) use ($db, $qSchema): bool {
            $stmt = $db->query("
                SELECT COUNT(*) FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = " . $db->quote($table) . "
            ");
            return (int) $stmt->fetchColumn() > 0;
        };

        $columnExists = static function (string $table, string $col) use ($db, $qSchema): bool {
            $stmt = $db->query("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = " . $db->quote($table) . "
                  AND COLUMN_NAME = " . $db->quote($col) . "
            ");
            return (int) $stmt->fetchColumn() > 0;
        };

        $hasLegacy = $tableExists('campaigns_offers');
        $hasTarget = $tableExists('campaign_offers');

        if ($hasLegacy && !$hasTarget) {
            $db->exec('RENAME TABLE campaigns_offers TO campaign_offers');
            $hasTarget = true;
        }

        if (!$hasTarget) {
            $db->exec("
                CREATE TABLE campaign_offers (
                    id INT NOT NULL AUTO_INCREMENT,
                    campaign_id INT NOT NULL,
                    offer_id INT NOT NULL,
                    current_views INT NULL DEFAULT NULL,
                    cap INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_campaign_offer (campaign_id, offer_id),
                    KEY idx_campaign_offers_offer (offer_id),
                    KEY idx_campaign_offers_campaign (campaign_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            return;
        }

        foreach (
            [
                'current_views' => 'INT NULL DEFAULT NULL',
                'cap' => 'INT NOT NULL DEFAULT 0',
                'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ] as $col => $def
        ) {
            if (!$columnExists('campaign_offers', $col)) {
                $db->exec("ALTER TABLE campaign_offers ADD COLUMN $col $def");
            }
        }

        $hasUq = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaign_offers' AND INDEX_NAME = 'uq_campaign_offer'
        ")->fetchColumn();
        if (!$hasUq) {
            try {
                $db->exec('ALTER TABLE campaign_offers ADD UNIQUE KEY uq_campaign_offer (campaign_id, offer_id)');
            } catch (Throwable $e) {
            }
        }
    },

    'down' => function (PDO $db): void {
    },
];
