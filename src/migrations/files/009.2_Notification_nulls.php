<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications MODIFY commission_id VARCHAR(255) NOT NULL");
        $db->exec("ALTER TABLE notifications MODIFY revenue decimal(10,2) NOT NULL");
        $db->exec("ALTER TABLE notifications MODIFY event_type tinyint(4) NULL");
        $db->exec("ALTER TABLE notifications MODIFY event_id VARCHAR(255) NULL");
        $db->exec("ALTER TABLE notifications MODIFY is_read tinyint(1) NULL");




    },
 
    'down' => function (PDO $db) {

        $db->exec("ALTER TABLE notifications MODIFY commission_id VARCHAR(255) NULL");
        $db->exec("ALTER TABLE notifications MODIFY revenue decimal(10,2) NULL");
        $db->exec("ALTER TABLE notifications MODIFY event_type tinyint(4) NULL");
        $db->exec("ALTER TABLE notifications MODIFY event_id VARCHAR(255) NULL");
        $db->exec("ALTER TABLE notifications MODIFY is_read tinyint(1) NULL");
    }
];
