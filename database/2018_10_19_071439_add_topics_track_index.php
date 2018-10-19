<?php
namespace Misuzu\DatabaseMigrations\AddTopicsTrackIndex;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_forum_topics_track`
            ADD INDEX `forum_track_last_read` (`track_last_read`);
    ');

    // i am actually brain dead, holy shit
    $conn->exec('
        ALTER TABLE `msz_forum_permissions`
            DROP INDEX `forum_permissions_forum_id_unique`,
            ADD UNIQUE INDEX `forum_permissions_unique` (`user_id`, `role_id`, `forum_id`),
            DROP INDEX `forum_permissions_user_id_unique`,
            ADD INDEX `forum_permissions_forum_id` (`forum_id`),
            DROP INDEX `forum_permissions_role_id_unique`,
            ADD INDEX `forum_permissions_role_id` (`role_id`);
    ');
}

function migrate_down(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE `msz_forum_permissions`
            DROP INDEX `forum_permissions_unique`,
            ADD UNIQUE INDEX `forum_permissions_user_id_unique` (`user_id`),
            DROP INDEX `forum_permissions_forum_id`,
            ADD INDEX `forum_permissions_role_id_unique` (`role_id`),
            DROP INDEX `forum_permissions_role_id`,
            ADD INDEX `forum_permissions_forum_id_unique` (`forum_id`);
    ');

    $conn->exec('
        ALTER TABLE `msz_forum_topics_track`
            DROP INDEX `forum_track_last_read`;
    ');
}
