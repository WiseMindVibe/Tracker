<?php

return [
    'up' => function (PDO $db) {

        // Add new column
        $db->exec("ALTER TABLE campaigns
            ADD COLUMN uuid CHAR(36) NOT NULL AFTER name
        ");

        $db->exec("CREATE INDEX idx_campaign_uuid ON campaigns (uuid)");
    },

    'down' => function (PDO $db) {

        // Drop column
        $db->exec("ALTER TABLE campaigns
            DROP COLUMN uuid
        ");
    }
];
