<?php
namespace Misuzu\DatabaseMigrations\AuditLogStruct;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_permissions`
            RENAME INDEX `user_id` TO `permissions_user_id_unique`,
            RENAME INDEX `role_id` TO `permissions_role_id_unique`,
            DROP FOREIGN KEY `role_id_foreign`,
            DROP FOREIGN KEY `user_id_foreign`,
            ADD CONSTRAINT `permissions_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            ADD CONSTRAINT `permissions_role_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
    ');

    $conn->exec("
        CREATE TABLE `msz_audit_log` (
            `log_id`        INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `user_id`       INT(10) UNSIGNED    NULL        DEFAULT NULL,
            `log_action`    VARCHAR(50)         NOT NULL,
            `log_params`    TEXT                NOT NULL,
            `log_created`   TIMESTAMP           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
            `log_ip`        VARBINARY(16)       NULL        DEFAULT NULL,
            PRIMARY KEY (`log_id`),
            INDEX `audit_log_user_id_foreign` (`user_id`),
            CONSTRAINT `audit_log_user_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        )
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('DROP TABLE `msz_audit_log`');

    $conn->exec('
        ALTER TABLE `msz_permissions`
            RENAME INDEX `permissions_user_id_unique` TO `user_id`,
            RENAME INDEX `permissions_role_id_unique` TO `role_id`,
            DROP FOREIGN KEY `permissions_user_id_foreign`,
            DROP FOREIGN KEY `permissions_role_id_foreign`,
            ADD CONSTRAINT `role_id_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            ADD CONSTRAINT `user_id_foreign`
                FOREIGN KEY (`role_id`)
                REFERENCES `msz_roles` (`role_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
    ');
}
