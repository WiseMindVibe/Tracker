<?php

return [
    'up' => function (PDO $db) {


        $db->exec("ALTER TABLE notifications DROP COLUMN click_created_at");
    },
 
    'down' => function (PDO $db) {

        // Drop column
        $db->exec("ALTER TABLE notifications ADD COLUMN click_created_at DATETIME NOT NULL");
    }
];
