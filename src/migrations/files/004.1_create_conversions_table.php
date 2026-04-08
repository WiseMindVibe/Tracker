<?php

return [
    'up' => function (PDO $db) {

        // Add new column
        $db->exec("CREATE TABLE IF NOT EXISTS conversions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            click_id BIGINT NOT NULL,
            commission_id VARCHAR(255) NOT NULL, -- need to update via POSTBACK --
            revenue DECIMAL(10,2) DEFAULT 0.00, -- replace with payout from clicks table --
            status tinyint(1) NOT NULL DEFAULT '1', -- 0: unknowm, 1: open, 2: confirmed, 3: rejected, 4: paid --
            sale_date DATETIME NULL, -- sale time --
            modified_date DATETIME NULL, -- event time --
            event_type VARCHAR(255) NULL, -- either NEW or UPDATE --
            event_id VARCHAR(255) NULL,
            advertiser_id VARCHAR(255) NULL,
            sales_amount DECIMAL(10,2) DEFAULT 0.00 -- FOR TESTING, PROBABLY REMOVE --
        )
        ");

        $db->exec("INSERT INTO conversions (click_id, revenue, status)
            SELECT
                c.id,
                c.payout,
                CASE
                    WHEN c.status = 'open' THEN 1
                    WHEN c.status = 'confirmed' THEN 2
                    WHEN c.status = 'rejected' THEN 3
                    WHEN c.status = 'paid' THEN 4
                    ELSE 0
                END
            FROM clicks c
            WHERE c.status IS NOT NULL;
        ");

        $db->exec("ALTER TABLE notifications CHANGE payout revenue DECIMAL(10,2);");
        
        $db->exec("ALTER TABLE notifications MODIFY revenue DECIMAL(10,2) AFTER affiliate_id;");
        
    },
 
    'down' => function (PDO $db) {

        // Drop column
        $db->exec("DROP TABLE conversions");
    }
];
