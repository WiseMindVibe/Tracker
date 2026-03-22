<?php

return [
    'up' => function (PDO $db) {

        // Add new column
        $db->exec("ALTER TABLE offers
            ADD COLUMN affiliate_program_id_new INT NULL AFTER affiliate_program_id
        ");

        // Add foreign key
        $db->exec("ALTER TABLE offers
            ADD CONSTRAINT fk_offers_affiliate_account
            FOREIGN KEY (affiliate_program_id_new)
            REFERENCES affiliate_accounts(id)
        ");

        $db->exec("UPDATE offers
        SET affiliate_program_id_new = affiliate_program_id
        ");
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
    }
];
