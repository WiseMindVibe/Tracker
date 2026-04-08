<?php

return [
    'up' => function (PDO $db) {

        $db->exec("ALTER TABLE clicks

            ADD COLUMN device VARCHAR(32) NULL AFTER status,
            ADD COLUMN os_version VARCHAR(32) NULL AFTER os,
            ADD COLUMN browser_version VARCHAR(32) NULL AFTER browser,

            ADD COLUMN connection_type VARCHAR(32) NULL AFTER browser_version,
            ADD COLUMN isp VARCHAR(128) NULL AFTER connection_type,
            ADD COLUMN carrier VARCHAR(128) NULL AFTER isp,

            ADD COLUMN subzone_id VARCHAR(64) NULL AFTER zone_id,

            ADD COLUMN useragent TEXT NULL AFTER subzone_id,
            ADD COLUMN user_activity VARCHAR(64) NULL AFTER useragent,

            ADD COLUMN region VARCHAR(64) NULL AFTER country,
            ADD COLUMN language VARCHAR(16) NULL AFTER region
        ");

    },

    'down' => function (PDO $db) {

        $db->exec("
            ALTER TABLE clicks

            DROP COLUMN device,
            DROP COLUMN os_version,
            DROP COLUMN browser_version,
            DROP COLUMN connection_type,
            DROP COLUMN isp,
            DROP COLUMN carrier,
            DROP COLUMN sub_zone_id,
            DROP COLUMN useragent,
            DROP COLUMN user_activity,
            DROP COLUMN region,
            DROP COLUMN language
        ");

    }
];