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

        //Insert existing affiliate programs into new affiliate_accounts table
        $db->exec("INSERT INTO affiliate_accounts (id, affiliate_program, active, created_at, updated_at)
            SELECT id, name, 1, created_at, updated_at
            FROM affiliate_programs
            WHERE id NOT IN (
                SELECT id FROM affiliate_accounts
            )
        ");

        //Insert Oponia
        $db->exec("INSERT INTO affiliate_account_credentials (affiliate_account_id, field_key, field_value, is_required)
            SELECT 1, 'API-Key', ap.api_key, 1
            FROM affiliate_programs ap
            WHERE ap.id = 1
            ");

        //Insert Yieldkit
        $db->exec("INSERT INTO affiliate_account_credentials (affiliate_account_id, field_key, field_value, is_required)
            SELECT 2, 'API-Key', ap.api_key, 1
            FROM affiliate_programs ap
            WHERE ap.id = 2
            UNION ALL
            SELECT 2, 'API-Secret', ap.api_secret, 1
            FROM affiliate_programs ap
            WHERE ap.id = 2
            ");

        // Add new column
        $db->exec("ALTER TABLE offers
            ADD COLUMN affiliate_program_id_new INT NULL AFTER affiliate_program_id
        ");

        // Add foreign key
        $db->exec("ALTER TABLE offers
            ADD CONSTRAINT fk_affiliate_program_id
            FOREIGN KEY (affiliate_program_id_new)
            REFERENCES affiliate_accounts(id)
        ");

        $db->exec("UPDATE offers
        SET affiliate_program_id_new = affiliate_program_id
        ");

        $db->exec("ALTER TABLE offers
            DROP COLUMN affiliate_program_id 
        ");

        $db->exec("ALTER TABLE offers
            CHANGE affiliate_program_id_new affiliate_program_id INT 
        ");

        $db->exec("ALTER TABLE offers DROP FOREIGN KEY fk_affiliate_program_id");

        $db->exec("ALTER TABLE affiliate_accounts DROP FOREIGN KEY fk_affiliate_accounts");

        $db->exec("ALTER TABLE affiliate_account_credentials DROP FOREIGN KEY fk_credentials_account");

        $db->exec("DROP TABLE affiliate_programs");
    },


    'down' => function (PDO $db) {

        // Drop FK first
        $db->exec("
            ALTER TABLE offers
            DROP FOREIGN KEY fk_offers_affiliate_account
        ");

        // Drop column
        $db->exec("
            ALTER TABLE offers
            DROP COLUMN affiliate_program_id_new
        ");

        //Drop Tables
        $db->exec("DROP TABLE IF EXISTS affiliate_account_credentials");
        $db->exec("DROP TABLE IF EXISTS affiliate_accounts");
    }

];
