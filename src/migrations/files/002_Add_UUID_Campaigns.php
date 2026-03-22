<?php

/**
 * Adds campaigns.uuid (CHAR(36)) with a unique index.
 * Existing rows must each get a distinct UUID — NOT NULL + empty string breaks UNIQUE.
 */

$genUuidV4 = static function (): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
};

return [
    'up' => function (PDO $db) use ($genUuidV4): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if (!$schema) {
            throw new RuntimeException('No database selected');
        }
        $qSchema = $db->quote((string) $schema);

        $hasUuid = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaigns' AND COLUMN_NAME = 'uuid'
        ")->fetchColumn();

        if (!$hasUuid) {
            $db->exec("
                ALTER TABLE campaigns
                ADD COLUMN uuid CHAR(36) NULL DEFAULT NULL AFTER name
            ");
        }

        $stmt = $db->query("
            SELECT id FROM campaigns
            WHERE uuid IS NULL
               OR TRIM(uuid) = ''
               OR CHAR_LENGTH(TRIM(uuid)) <> 36
            ORDER BY id
        ");
        $needIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $upd = $db->prepare('UPDATE campaigns SET uuid = ? WHERE id = ?');
        foreach ($needIds as $id) {
            $upd->execute([$genUuidV4(), $id]);
        }

        // Resolve duplicate UUIDs (keep lowest id, reassign the rest)
        $dupStmt = $db->query("
            SELECT uuid FROM campaigns
            WHERE uuid IS NOT NULL AND TRIM(uuid) <> ''
            GROUP BY uuid
            HAVING COUNT(*) > 1
        ");
        $dupUuids = $dupStmt->fetchAll(PDO::FETCH_COLUMN);
        $selDup = $db->prepare('SELECT id FROM campaigns WHERE uuid = ? ORDER BY id');
        foreach ($dupUuids as $uuidVal) {
            $selDup->execute([$uuidVal]);
            $ids = $selDup->fetchAll(PDO::FETCH_COLUMN);
            array_shift($ids);
            foreach ($ids as $dupId) {
                $upd->execute([$genUuidV4(), $dupId]);
            }
        }

        $db->exec('ALTER TABLE campaigns MODIFY uuid CHAR(36) NOT NULL');

        $hasIdx = (int) $db->query("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = $qSchema
              AND TABLE_NAME = 'campaigns'
              AND INDEX_NAME = 'idx_campaign_uuid'
        ")->fetchColumn();

        if (!$hasIdx) {
            $db->exec('CREATE UNIQUE INDEX idx_campaign_uuid ON campaigns (uuid)');
        }
    },

    'down' => function (PDO $db): void {
        $schema = $db->query('SELECT DATABASE()')->fetchColumn();
        if ($schema) {
            $qSchema = $db->quote((string) $schema);
            $hasIdx = (int) $db->query("
                SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = $qSchema
                  AND TABLE_NAME = 'campaigns'
                  AND INDEX_NAME = 'idx_campaign_uuid'
            ")->fetchColumn();
            if ($hasIdx) {
                $db->exec('ALTER TABLE campaigns DROP INDEX idx_campaign_uuid');
            }

            $hasCol = (int) $db->query("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = $qSchema AND TABLE_NAME = 'campaigns' AND COLUMN_NAME = 'uuid'
            ")->fetchColumn();
            if ($hasCol) {
                $db->exec('ALTER TABLE campaigns DROP COLUMN uuid');
            }
        }
    },
];
