<?php

return [
    'up' => function (PDO $db) {

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
    },

    'down' => function (PDO $db) {


    }
];
