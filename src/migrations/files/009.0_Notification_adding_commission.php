<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications ADD COLUMN commission_id VARCHAR(255) NULL AFTER click_id;");



    },
 
    'down' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications DROP COLUMN commission_id;");
    }
];
