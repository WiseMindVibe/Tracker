<?php

return [
    'up' => function (PDO $db) {

        // Add new column
        $db->exec("ALTER TABLE offers_artlcles
        RENAME TO offers_articles
        ");
    },

    'down' => function (PDO $db) {

        // Drop column
        $db->exec("ALTER TABLE offers_articles
        RENAME TO offers_artlcles
        ");
    }
];
