<?php
namespace Misuzu\DatabaseMigrations\RemoveTriggerFromTopicsTrack;

use PDO;

function migrate_up(PDO $conn): void
{
    $conn->exec("DROP TRIGGER `msz_forum_topics_track_increase_views`");
}

function migrate_down(PDO $conn): void
{
    $conn->exec("
        CREATE TRIGGER `msz_forum_topics_track_increase_views`
        AFTER INSERT ON `msz_forum_topics_track`
        FOR EACH ROW BEGIN
            UPDATE `msz_forum_topics`
            SET `topic_count_views` = `topic_count_views` + 1
            WHERE `topic_id` = NEW.topic_id;
        END;
    ");
}
