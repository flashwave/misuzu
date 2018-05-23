<?php
use Misuzu\Database;

function forum_post_create(
    int $topicId,
    int $forumId,
    int $userId,
    string $ipAddress,
    string $text
): int {
    $dbc = Database::connection();

    $createPost = $dbc->prepare('
        INSERT INTO `msz_forum_posts`
            (`topic_id`, `forum_id`, `user_id`, `post_ip`, `post_text`)
        VALUES
            (:topic_id, :forum_id, :user_id, INET6_ATON(:post_ip), :post_text)
    ');
    $createPost->bindValue('topic_id', $topicId);
    $createPost->bindValue('forum_id', $forumId);
    $createPost->bindValue('user_id', $userId);
    $createPost->bindValue('post_ip', $ipAddress);
    $createPost->bindValue('post_text', $text);

    return $createPost->execute() ? $dbc->lastInsertId() : 0;
}

function forum_post_find(int $postId): array
{
    $getPostInfo = Database::connection()->prepare('
        SELECT
        :post_id as `target_post_id`,
        (
            SELECT `topic_id`
            FROM `msz_forum_posts`
            WHERE `post_id` = `target_post_id`
        ) as `target_topic_id`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `topic_id` = `target_topic_id`
            AND `post_id` < `target_post_id`
            ORDER BY `post_id`
        ) as `preceeding_post_count`
    ');
    $getPostInfo->bindValue('post_id', $postId);

    return $getPostInfo->execute() ? $getPostInfo->fetch() : false;
}
