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

            ADD COLUMN bannerid VARCHAR(64) NULL AFTER user_activity,

            ADD COLUMN region VARCHAR(64) NULL AFTER country,
            ADD COLUMN language VARCHAR(16) NULL AFTER region,

            ADD INDEX idx_zone_id (zone_id),
            ADD INDEX idx_subzone_id (subzone_id),
            ADD INDEX idx_region (region),
            ADD INDEX idx_banner (bannerid),

            ADD INDEX idx_device (device),
            ADD INDEX idx_os (os),
            ADD INDEX idx_browser (browser),

            ADD INDEX idx_connection_type (connection_type),
            ADD INDEX idx_isp (isp),
            ADD INDEX idx_carrier (carrier)
        ");

    },

    'down' => function (PDO $db) {

        $db->exec("
            ALTER TABLE clicks

            DROP INDEX idx_zone_id,
            DROP INDEX idx_sub_zone_id,
            DROP INDEX idx_country,
            DROP INDEX idx_region,
            DROP INDEX idx_banner,

            DROP INDEX idx_device,
            DROP INDEX idx_os,
            DROP INDEX idx_browser,

            DROP INDEX idx_connection_type,
            DROP INDEX idx_isp,
            DROP INDEX idx_carrier,

            DROP COLUMN device,
            DROP COLUMN os_version,
            DROP COLUMN browser_version,
            DROP COLUMN connection_type,
            DROP COLUMN isp,
            DROP COLUMN carrier,
            DROP COLUMN sub_zone_id,
            DROP COLUMN useragent,
            DROP COLUMN user_activity,
            DROP COLUMN bannerid,
            DROP COLUMN region,
            DROP COLUMN language
        ");

    }
];