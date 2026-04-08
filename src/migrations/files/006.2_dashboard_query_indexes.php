<?php

return [

    'up' => function (PDO $db) {

        $ignoreDuplicate = static function (callable $fn): void {
            try {
                $fn();
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Duplicate key name')
                    || str_contains($msg, 'duplicate key name')
                    || str_contains($msg, 'Duplicate foreign key')
                    || str_contains($msg, 'already exists')) {
                    return;
                }
                throw $e;
            }
        };

        $ignoreDuplicate(fn () => $db->exec("
            ALTER TABLE clicks
            ADD INDEX idx_clicks_created_at (created_at)
        "));

        $ignoreDuplicate(fn () => $db->exec("
            ALTER TABLE conversions
            ADD INDEX idx_conversions_click_id (click_id)
        "));
    },

    'down' => function (PDO $db) {

        $ignoreMissing = static function (callable $fn): void {
            try {
                $fn();
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Unknown key')
                    || str_contains($msg, 'check that column/key exists')
                    || str_contains($msg, "Can't DROP")
                    || str_contains($msg, 'does not exist')) {
                    return;
                }
                throw $e;
            }
        };

        $ignoreMissing(fn () => $db->exec('ALTER TABLE conversions DROP INDEX idx_conversions_click_id'));

        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_at'));
    },

];
