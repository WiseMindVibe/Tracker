<?php

return [
    'up' => function (PDO $db) {
        $ignoreDuplicate = static function (callable $fn): void {
            try {
                $fn();
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Duplicate key name')
                    || str_contains($msg, 'duplicate key name')
                    || str_contains($msg, 'already exists')
                ) {
                    return;
                }
                throw $e;
            }
        };

        $ignoreMissing = static function (callable $fn): void {
            try {
                $fn();
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Unknown key')
                    || str_contains($msg, 'check that column/key exists')
                    || str_contains($msg, "Can't DROP")
                    || str_contains($msg, 'does not exist')
                ) {
                    return;
                }
                throw $e;
            }
        };

        // Replace legacy v2 reporting indexes from old 007/008 attempts.
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_offer_created'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_campaign_created'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_os'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_browser'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_os'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_browser'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE conversions DROP INDEX idx_conversions_status'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE conversions DROP INDEX idx_conversions_created_at'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE conversions DROP INDEX idx_conversions_click_status'));

        // Offer -> campaign -> os -> browser hierarchy
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_created_offer (created_at, offer_id)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_offer_created_campaign (offer_id, created_at, campaign_id)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_offer_campaign_created_os (offer_id, campaign_id, created_at, `OS`)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_offer_campaign_os_created_browser (offer_id, campaign_id, `OS`, created_at, browser)'));

        // Campaign -> offer -> os -> browser hierarchy
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_created_campaign (created_at, campaign_id)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_campaign_created_offer (campaign_id, created_at, offer_id)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_campaign_offer_created_os (campaign_id, offer_id, created_at, `OS`)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_campaign_offer_os_created_browser (campaign_id, offer_id, `OS`, created_at, browser)'));

        // Top-level os/browser grouping by date range.
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_created_at_os (created_at, `OS`)'));
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE clicks ADD INDEX idx_clicks_created_at_browser (created_at, browser)'));

        // Conversion join pattern in reporting queries.
        $ignoreDuplicate(fn () => $db->exec('ALTER TABLE conversions ADD INDEX idx_conversions_click_status (click_id, status)'));
    },

    'down' => function (PDO $db) {
        $ignoreMissing = static function (callable $fn): void {
            try {
                $fn();
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Unknown key')
                    || str_contains($msg, 'check that column/key exists')
                    || str_contains($msg, "Can't DROP")
                    || str_contains($msg, 'does not exist')
                ) {
                    return;
                }
                throw $e;
            }
        };

        $ignoreMissing(fn () => $db->exec('ALTER TABLE conversions DROP INDEX idx_conversions_click_status'));

        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_at_browser'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_at_os'));

        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_campaign_offer_os_created_browser'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_campaign_offer_created_os'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_campaign_created_offer'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_campaign'));

        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_offer_campaign_os_created_browser'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_offer_campaign_created_os'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_offer_created_campaign'));
        $ignoreMissing(fn () => $db->exec('ALTER TABLE clicks DROP INDEX idx_clicks_created_offer'));
    },
];
