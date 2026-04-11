<?php

return [

    'up' => function (PDO $db) {

        // =========================
        // Core — base (no inward FKs)
        // =========================
    
        $db->exec("CREATE TABLE IF NOT EXISTS affiliate_programs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            api_key VARCHAR(255) DEFAULT NULL,
            api_secret VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS traffic_sources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            api_key VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS websites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(64) NOT NULL,
            country CHAR(2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at)

        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // =========================
        // Core — campaigns & offers
        // =========================
    
        $db->exec("CREATE TABLE IF NOT EXISTS campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            country CHAR(2) NOT NULL,
            traffic_source_id INT NOT NULL,
            tester TINYINT(1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(70) NOT NULL,
            affiliate_program_id INT NOT NULL,
            affiliate_link VARCHAR(2083) NOT NULL,
            country CHAR(2) NOT NULL,
            website_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_affiliate_program (affiliate_program_id),
            INDEX idx_website (website_id),
            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at),

            CONSTRAINT fk_affiliate_program
                FOREIGN KEY (affiliate_program_id)
                REFERENCES affiliate_programs(id)
                ON DELETE CASCADE,

            CONSTRAINT fk_website
                FOREIGN KEY (website_id)
                REFERENCES websites(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // =========================
        // Core — junctions & facts (depend on campaigns / offers / traffic)
        // =========================
    
        $db->exec("CREATE TABLE IF NOT EXISTS `campaign_external_ids` (
            `id` int(11) AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` int(11) NOT NULL,
            `external_campaign_id` varchar(255) NOT NULL,
            `traffic_source_id` int(11) NOT NULL,

            INDEX campaign_id (campaign_id),
            INDEX fx_traffic_source (traffic_source_id),

            CONSTRAINT campaign_external_ids_ibfk_1 FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            CONSTRAINT fx_traffic_source FOREIGN KEY (traffic_source_id) REFERENCES traffic_sources(id) ON DELETE CASCADE
            
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS campaign_offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            offer_id INT NOT NULL,
            current_views INT DEFAULT NULL,
            cap INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX campaign_id (campaign_id),
            INDEX offer_id (offer_id),
            INDEX idx_created_at (created_at),
            INDEX idx_updated_at (updated_at),

            CONSTRAINT campaign_offers_ibfk_1 FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
            CONSTRAINT campaign_offers_ibfk_2 FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS clicks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            click_id VARCHAR(64) NOT NULL,
            offer_id INT NOT NULL,
            campaign_id INT NOT NULL,
            country CHAR(2) NOT NULL,
            payout DECIMAL(10,5) NOT NULL,
            status ENUM('Open','Confirmed','Paid','Rejected') DEFAULT NULL,
            OS VARCHAR(50) NOT NULL,
            browser VARCHAR(50) NOT NULL,
            zone_id VARCHAR(64) NOT NULL,
            ip VARBINARY(16) NOT NULL,
            cost DECIMAL(10,5) NOT NULL,
            event_id VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
            INDEX idx_offer_id (offer_id),
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_click_id (click_id),
            INDEX idx_status (status),
            INDEX idx_country (country),
            INDEX idx_created_time (created_at),
            INDEX idx_updated_time (updated_at),
        
            CONSTRAINT fk_click_offer
                FOREIGN KEY (offer_id)
                REFERENCES offers(id)
                ON DELETE CASCADE,
        
            CONSTRAINT fk_click_campaign
                FOREIGN KEY (campaign_id)
                REFERENCES campaigns(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` bigint(20) AUTO_INCREMENT PRIMARY KEY,
            `click_id` bigint(20) NOT NULL,
            `offer_id` int(11) NOT NULL,
            `affiliate_id` int(11) NOT NULL,
            `status` enum('open','confirmed','rejected','paid') NOT NULL,
            `payout` decimal(10,2) DEFAULT 0.00,
            `click_created_at` datetime NOT NULL,
            `click_updated_at` datetime NOT NULL,
            `is_read` tinyint(1) DEFAULT 0,
            `read_at` timestamp NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),

            INDEX is_read (is_read),
            INDEX created_at (created_at),
            INDEX affiliate_id (affiliate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS `offers_artlcles` (
            `id` int(11) AUTO_INCREMENT PRIMARY KEY,
            `offer_id` int(11) NOT NULL,
            `article_url` varchar(100) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS websites_buffers (
            `id` int(11) AUTO_INCREMENT PRIMARY KEY,
            `website_id` int(11) NOT NULL,
            `type` varchar(25) NOT NULL,
            `buffer_url` varchar(50) NOT NULL,

            INDEX fk_website2 (website_id),
            
            CONSTRAINT `fk_website2` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // =========================
        // Logs / system
        // =========================
    
        $db->exec("CREATE TABLE IF NOT EXISTS `log_campaign` (
            `id` int(11) AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` int(11) NOT NULL,
            `offer_id` int(11) NOT NULL,
            `action` varchar(255) NOT NULL,
            `roi` float DEFAULT NULL,
            `log_time` datetime DEFAULT current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS postback_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('Missing parameters','Missing click_id','Invalid status','Invalid payout','Click not found') NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            click_id VARCHAR(64) DEFAULT NULL,
            raw_query VARCHAR(255) NOT NULL,
            ip VARBINARY(16) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_click_id (click_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS redirect_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('Missing campaign_id','Campaign not found') DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            campaign_id VARCHAR(64) NOT NULL,
            raw_query VARCHAR(255) NOT NULL,
            ip VARBINARY(16) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_campaign_id (campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        $db->exec("CREATE TABLE IF NOT EXISTS `traffic_source_logs` (
            `id` int(10) UNSIGNED NOT NULL,
            `traffic_source_id` int(10) UNSIGNED NOT NULL,
            `campaign_id` bigint(20) UNSIGNED NOT NULL,
            `response` text NOT NULL,
            `http_code` smallint(5) UNSIGNED NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),

            INDEX idx_traffic_source_id (traffic_source_id),
            INDEX idx_campaign_id (campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    },

    'down' => function (PDO $db) {
        // Intentionally empty — foundation should not be dropped
    }

];
