<?php

return [

    'up' => function (PDO $db) {

        // =========================
        // Core Tables
        // =========================
    
        //affiliate_programs gets deleted
    
        //Don't touch campaigns
    
        //campaign external ids
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        $db->exec("ALTER TABLE campaign_external_ids DROP FOREIGN KEY fx_traffic_source");
        $db->exec("ALTER TABLE campaign_external_ids DROP INDEX fx_traffic_source");
        $db->exec("ALTER TABLE campaign_external_ids DROP FOREIGN KEY campaign_external_ids_ibfk_1");
        $db->exec("ALTER TABLE campaign_external_ids DROP INDEX campaign_id");
        //$db->exec("ALTER TABLE campaign_external_ids ADD CONSTRAINT fk_campaigns FOREIGN KEY (campaign_id) REFERENCES campaigns(id)");
        $db->exec("ALTER TABLE campaign_external_ids DROP COLUMN traffic_source_id");



        //campaign offers
        $db->exec("ALTER TABLE campaign_offers DROP FOREIGN KEY campaign_offers_ibfk_1");
        $db->exec("ALTER TABLE campaign_offers DROP FOREIGN KEY campaign_offers_ibfk_2");

        $db->exec("ALTER TABLE campaign_offers DROP INDEX campaign_id");
        $db->exec("ALTER TABLE campaign_offers DROP INDEX offer_id");
        
        //$db->exec("ALTER TABLE campaign_offers ADD CONSTRAINT fk_offers FOREIGN KEY (offer_id) REFERENCES offers(id)");

        //clicks
        $db->exec("ALTER TABLE clicks DROP FOREIGN KEY fk_click_offer");
        $db->exec("ALTER TABLE clicks DROP FOREIGN KEY fk_click_campaign");

        $db->exec("ALTER TABLE clicks DROP INDEX idx_offer_id");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_campaign_id");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_click_id");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_status");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_country");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_created_time");
        $db->exec("ALTER TABLE clicks DROP INDEX idx_updated_time");

        $db->exec("ALTER TABLE clicks MODIFY zone_id VARCHAR(64) NULL");
        $db->exec("ALTER TABLE clicks MODIFY event_id VARCHAR(64) NULL");

        //notifications
        $db->exec("ALTER TABLE notifications DROP INDEX is_read");
        $db->exec("ALTER TABLE notifications DROP INDEX created_at");
        $db->exec("ALTER TABLE notifications DROP INDEX affiliate_id");

        $db->exec("ALTER TABLE notifications ADD COLUMN campaign_id INT NOT NULL AFTER offer_id");
        $db->exec("ALTER TABLE notifications ADD COLUMN event_type tinyint(1) NOT NULL AFTER status");
        $db->exec("ALTER TABLE notifications ADD COLUMN event_id INT NOT NULL AFTER event_type");

        //offers
        $db->exec("ALTER TABLE offers DROP FOREIGN KEY fk_website");
        $db->exec("ALTER TABLE offers DROP FOREIGN KEY fk_affiliate_program");

        $db->exec("ALTER TABLE offers DROP INDEX idx_website");
        $db->exec("ALTER TABLE offers DROP INDEX idx_affiliate_program");

        //offer articles
        $db->exec("RENAME TABLE offers_artlcles TO offers_articles");

        //traffic sources
        $db->exec("ALTER TABLE traffic_sources DROP INDEX idx_created_at");
        $db->exec("ALTER TABLE traffic_sources DROP INDEX idx_updated_at");

        //websites
        $db->exec("ALTER TABLE websites DROP INDEX idx_created_at");
        $db->exec("ALTER TABLE websites DROP INDEX idx_updated_at");


        //website buffers
        $db->exec("ALTER TABLE websites_buffers DROP FOREIGN KEY fk_website2");
        $db->exec("ALTER TABLE websites_buffers DROP INDEX fk_website2");


        // =========================
        // Logs / System Tables
        // =========================
    


        $db->exec("DROP TABLE IF EXISTS traffic_source_logs");
        $db->exec("DROP TABLE IF EXISTS redirect_logs");
        $db->exec("DROP TABLE IF EXISTS postback_logs");
        $db->exec("DROP TABLE IF EXISTS log_campaign");

        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    },

    'down' => function (PDO $db) {
        // Intentionally empty — foundation should not be dropped
    }

];