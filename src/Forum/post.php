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

define('MSZ_FORUM_POST_LISTING_QUERY_STANDARD', '
    SELECT
        p.`post_id`, p.`post_text`, p.`post_created`,
        p.`topic_id`,
        u.`user_id` as `poster_id`,
        u.`username` as `poster_name`,
        u.`created_at` as `poster_joined`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `poster_colour`
    FROM `msz_forum_posts` as p
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = p.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE `topic_id` = :topic_id
    AND `post_deleted` IS NULL
    ORDER BY `post_id`
');
define('MSZ_FORUM_POST_LISTING_QUERY_PAGINATED', MSZ_FORUM_POST_LISTING_QUERY_STANDARD . ' LIMIT :offset, :take');

function forum_post_listing(int $topicId, int $offset = 0, int $take = 0): array
{
    $hasPagination = $offset >= 0 && $take > 0;
    $getPosts = Database::connection()->prepare(
        $hasPagination
        ? MSZ_FORUM_POST_LISTING_QUERY_PAGINATED
        : MSZ_FORUM_POST_LISTING_QUERY_STANDARD
    );
    $getPosts->bindValue('topic_id', $topicId);

    if ($hasPagination) {
        $getPosts->bindValue('offset', $offset);
        $getPosts->bindValue('take', $take);
    }

    return $getPosts->execute() ? $getPosts->fetchAll() : [];
}
