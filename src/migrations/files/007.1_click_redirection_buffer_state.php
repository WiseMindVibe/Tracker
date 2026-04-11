<?php

return [
    'up' => function (PDO $db) {
        // Step 1: temporarily allow both legacy and new states.
        $db->exec("
            ALTER TABLE click_redirections
            MODIFY state ENUM('INIT', 'SENT', 'RETURNED', 'BUFFER', 'FINALIZED') NOT NULL DEFAULT 'INIT'
        ");

        // Legacy flows used SENT/RETURNED. Collapse both to BUFFER before tightening the enum.
        $db->exec("UPDATE click_redirections SET state = 'BUFFER' WHERE state IN ('SENT', 'RETURNED')");

        // Step 2: tighten enum to the new flow only.
        $db->exec("
            ALTER TABLE click_redirections
            MODIFY state ENUM('INIT', 'BUFFER', 'FINALIZED') NOT NULL DEFAULT 'INIT'
        ");
    },

    'down' => function (PDO $db) {
        // Step 1: temporarily allow BUFFER so data can be remapped.
        $db->exec("
            ALTER TABLE click_redirections
            MODIFY state ENUM('INIT', 'SENT', 'RETURNED', 'BUFFER', 'FINALIZED') NOT NULL DEFAULT 'INIT'
        ");

        // Roll BUFFER back to RETURNED on downgrade.
        $db->exec("UPDATE click_redirections SET state = 'RETURNED' WHERE state = 'BUFFER'");

        // Step 2: restore legacy enum.
        $db->exec("
            ALTER TABLE click_redirections
            MODIFY state ENUM('INIT', 'SENT', 'RETURNED', 'FINALIZED') NOT NULL DEFAULT 'INIT'
        ");
    },
];
