<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE campaign_external_ids
            DROP FOREIGN KEY fx_traffic_source 
        ");

        // Add new column
        $db->exec("ALTER TABLE campaign_external_ids
        DROP COLUMN traffic_source_id");
    },

    'down' => function (PDO $db) {
    }
];
