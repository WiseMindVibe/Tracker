<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications DROP COLUMN click_updated_at;");
        $db->exec("ALTER TABLE notifications ADD COLUMN sale_date DATETIME NULL;");
        $db->exec("ALTER TABLE notifications ADD COLUMN modified_date DATETIME NULL;");


    },
 
    'down' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications ADD COLUMN created_at DATETIME NOT NULL;");
        $db->exec("ALTER TABLE notifications ADD COLUMN click_created_at DATETIME NOT NULL;");
        $db->exec("ALTER TABLE notifications ADD COLUMN click_updated_at DATETIME NOT NULL;");
        $db->exec("ALTER TABLE notifications DROP COLUMN sale_date;");
        $db->exec("ALTER TABLE notifications DROP COLUMN modified_date;");
    }
];
