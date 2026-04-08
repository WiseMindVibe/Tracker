<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications
        CHANGE click_created_at created_at DATETIME NOT NULL;
        ");
    },
 
    'down' => function (PDO $db) {

        // Drop column
        $db->exec("ALTER TABLE notifications
        CHANGE created_at click_created_at DATETIME NOT NULL;
        ");
    }
];
