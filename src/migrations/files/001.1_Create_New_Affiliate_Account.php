<?php

return [

    'up' => function (PDO $db) {

        //. Affiliate accounts //YieldKit, Oponia etc
        $db->exec("CREATE TABLE IF NOT EXISTS affiliate_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                affiliate_program VARCHAR(50) NOT NULL,
                active TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
            
                CONSTRAINT fk_affiliate_accounts
                    FOREIGN KEY (id)
                    REFERENCES affiliate_programs(id)
                    ON DELETE CASCADE
            )
        ");

        //. Stored credentials
        $db->exec("CREATE TABLE IF NOT EXISTS affiliate_account_credentials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                affiliate_account_id INT NOT NULL,
                field_key VARCHAR(50) NOT NULL,
                field_value VARCHAR(100) NOT NULL,
                is_required TINYINT(1) DEFAULT  1,
                CONSTRAINT fk_credentials_account
                    FOREIGN KEY (affiliate_account_id)
                    REFERENCES affiliate_accounts(id)
                    ON DELETE CASCADE
            )
        ");
    },


    'down' => function (PDO $db) {

        //Drop Tables
        $db->exec("DROP TABLE IF EXISTS affiliate_account_credentials");
        $db->exec("DROP TABLE IF EXISTS affiliate_accounts");
    }

];
