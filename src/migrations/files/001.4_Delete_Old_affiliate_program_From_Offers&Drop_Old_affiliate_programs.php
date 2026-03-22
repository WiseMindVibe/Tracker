<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE offers
            DROP FOREIGN KEY fk_affiliate_program 
        ");

        $db->exec("ALTER TABLE offers
            DROP COLUMN affiliate_program_id 
        ");

        $db->exec("ALTER TABLE offers
            CHANGE affiliate_program_id_new affiliate_program_id INT 
        ");
        
        $db->exec("ALTER TABLE affiliate_accounts
        DROP FOREIGN KEY fk_affiliate_accounts");

        $db->exec("DROP TABLE affiliate_programs");
    },
    
    'down' => function (PDO $db) {


    }
];
