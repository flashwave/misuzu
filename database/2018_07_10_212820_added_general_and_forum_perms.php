<?php
namespace Misuzu\DatabaseMigrations\AddedGeneralAndForumPerms;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_permissions`
            ADD COLUMN `general_perms_allow`    INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `role_id`,
            ADD COLUMN `general_perms_deny`     INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `general_perms_allow`,
            ADD COLUMN `forum_perms_allow`      INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `news_perms_deny`,
            ADD COLUMN `forum_perms_deny`       INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `forum_perms_allow`,
            ADD COLUMN `comments_perms_allow`   INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `forum_perms_deny`,
            ADD COLUMN `comments_perms_deny`    INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `comments_perms_allow`;
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_permissions`
            DROP COLUMN `general_perms_allow`,
            DROP COLUMN `general_perms_deny`,
            DROP COLUMN `forum_perms_allow`,
            DROP COLUMN `forum_perms_deny,
            DROP COLUMN `forum_perms_allow`,
            DROP COLUMN `forum_perms_deny`;
    ');
}
