<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE clicks
            DROP COLUMN payout,
            DROP COLUMN status
        ");
    },
 
    'down' => function (PDO $db) {
    
    }
];
