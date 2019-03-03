<?php
namespace Misuzu\DatabaseMigrations\AddStaticTopicViewcount;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            ADD COLUMN `topic_count_views` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `topic_title`;
    ");

    $conn->exec("
        CREATE TRIGGER `msz_forum_topics_track_increase_views`
        AFTER INSERT ON `msz_forum_topics_track`
        FOR EACH ROW BEGIN
            UPDATE `msz_forum_topics`
            SET `topic_count_views` = `topic_count_views` + 1
            WHERE `topic_id` = NEW.topic_id;
        END;
    ");

    // Restore view counts
    $conn->exec("
        UPDATE `msz_forum_topics` AS t
        INNER JOIN (
            SELECT `topic_id`, COUNT(`user_id`) AS `count_views`
            FROM `msz_forum_topics_track`
            GROUP BY `topic_id`
        ) AS tt
        ON tt.`topic_id` = t.`topic_id`
        SET t.`topic_count_views` = tt.`count_views`
    ");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("DROP TRIGGER `msz_forum_topics_track_increase_views`");
    $conn->exec("
        ALTER TABLE `msz_forum_topics`
            DROP COLUMN `topic_count_views`;
    ");
}
