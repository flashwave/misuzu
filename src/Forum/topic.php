<?php
use Misuzu\Database;

define('MSZ_TOPIC_TYPE_DISCUSSION', 0);
define('MSZ_TOPIC_TYPE_STICKY', 1);
define('MSZ_TOPIC_TYPE_ANNOUNCEMENT', 2);
define('MSZ_TOPIC_TYPES', [
    MSZ_TOPIC_TYPE_DISCUSSION,
    MSZ_TOPIC_TYPE_STICKY,
    MSZ_TOPIC_TYPE_ANNOUNCEMENT,
]);

function forum_topic_create(int $forumId, int $userId, string $title): int
{
    $dbc = Database::connection();

    $createTopic = $dbc->prepare('
        INSERT INTO `msz_forum_topics`
            (`forum_id`, `user_id`, `topic_title`)
        VALUES
            (:forum_id, :user_id, :topic_title)
    ');
    $createTopic->bindValue('forum_id', $forumId);
    $createTopic->bindValue('user_id', $userId);
    $createTopic->bindValue('topic_title', $title);

    return $createTopic->execute() ? (int)$dbc->lastInsertId() : 0;
}

function forum_topic_bump(int $topicId): bool
{
    $bumpTopic = Database::connection()->prepare('
        UPDATE `msz_forum_topics`
        SET `topic_bumped` = NOW()
        WHERE `topic_id` = :topic_id
    ');
    $bumpTopic->bindValue('topic_id', $topicId);
    return $bumpTopic->execute();
}
