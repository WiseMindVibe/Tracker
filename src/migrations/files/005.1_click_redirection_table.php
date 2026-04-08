<?php

return [
    'up' => function (PDO $db) {

        $db->exec("CREATE TABLE IF NOT EXISTS click_redirections (
            click_id VARCHAR(255) PRIMARY KEY,
            domain VARCHAR(64) NOT NULL,
            affiliate_link VARCHAR(2083) NOT NULL,
            affiliate_token VARCHAR(255) NOT NULL,
            state ENUM('INIT', 'SENT', 'RETURNED', 'FINALIZED') NOT NULL DEFAULT 'INIT',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
    },
 
    'down' => function (PDO $db) {

        // Drop column
        $db->exec("DROP TABLE click_redirections");
    }
];
