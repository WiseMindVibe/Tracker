<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE conversions MODIFY commission_id VARCHAR(255) NULL;");

        $db->exec("UPDATE conversions SET commission_id = NULL;");

        $db->exec("ALTER TABLE conversions 
        ADD UNIQUE INDEX idx_conversions_commission_id (commission_id);");



    },
 
    'down' => function (PDO $db) {

        $db->exec("ALTER TABLE conversions DROP INDEX idx_conversions_commission_id;");
    }
];
