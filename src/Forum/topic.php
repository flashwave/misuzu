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

function forum_topic_fetch(int $topicId): array
{
    $getTopic = Database::connection()->prepare('
        SELECT
            t.`topic_id`, t.`forum_id`, t.`topic_title`, t.`topic_type`, t.`topic_locked`,
            f.`forum_archived` as `topic_archived`,
            (
                SELECT MIN(`post_id`)
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
            ) as `topic_first_post_id`,
            (
                SELECT COUNT(`post_id`)
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
            ) as `topic_post_count`
        FROM `msz_forum_topics` as t
        LEFT JOIN `msz_forum_categories` as f
        ON f.`forum_id` = t.`forum_id`
        WHERE t.`topic_id` = :topic_id
        AND t.`topic_deleted` IS NULL
    ');
    $getTopic->bindValue('topic_id', $topicId);
    $getTopic->execute();
    $topic = $getTopic->fetch();

    return $topic ? $topic : [];
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

function forum_topic_mark_read(int $userId, int $topicId, int $forumId): void
{
    if ($userId < 1) {
        return;
    }

    $markAsRead = Database::connection()->prepare('
        REPLACE INTO `msz_forum_topics_track`
            (`user_id`, `topic_id`, `forum_id`, `track_last_read`)
        VALUES
            (:user_id, :topic_id, :forum_id, NOW())
    ');
    $markAsRead->bindValue('user_id', $userId);
    $markAsRead->bindValue('topic_id', $topicId);
    $markAsRead->bindValue('forum_id', $forumId);
    $markAsRead->execute();
}

define('MSZ_TOPIC_LISTING_QUERY_STANDARD', '
    SELECT
        :user_id as `target_user_id`,
        t.`topic_id`, t.`topic_title`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
        au.`user_id` as `author_id`, au.`username` as `author_name`,
        COALESCE(ar.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `author_colour`,
        lp.`post_id` as `response_id`,
        lp.`post_created` as `response_created`,
        lu.`user_id` as `respondent_id`,
        lu.`username` as `respondent_name`,
        COALESCE(lr.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `respondent_colour`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `topic_id` = t.`topic_id`
        ) as `topic_post_count`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_forum_topics_track`
            WHERE `topic_id` = t.`topic_id`
        ) as `topic_view_count`,
        (
            SELECT
                `target_user_id` > 0
            AND
                t.`topic_bumped` > NOW() - INTERVAL 1 MONTH
            AND (
                SELECT COUNT(ti.`topic_id`) < 1
                FROM `msz_forum_topics_track` as tt
                RIGHT JOIN `msz_forum_topics` as ti
                ON ti.`topic_id` = tt.`topic_id`
                WHERE ti.`topic_id` = t.`topic_id`
                AND tt.`user_id` = `target_user_id`
                AND  `track_last_read` >= `topic_bumped`
            )
        ) as `topic_unread`
    FROM `msz_forum_topics` as t
    LEFT JOIN `msz_users` as au
    ON t.`user_id` = au.`user_id`
    LEFT JOIN `msz_roles` as ar
    ON ar.`role_id` = au.`display_role`
    LEFT JOIN `msz_forum_posts` as lp
    ON lp.`post_id` = (
        SELECT `post_id`
        FROM `msz_forum_posts`
        WHERE `topic_id` = t.`topic_id`
        ORDER BY `post_id` DESC
        LIMIT 1
    )
    LEFT JOIN `msz_users` as lu
    ON lu.`user_id` = lp.`user_id`
    LEFT JOIN `msz_roles` as lr
    ON lr.`role_id` = lu.`display_role`
    WHERE t.`forum_id` = :forum_id
    AND t.`topic_deleted` IS NULL
    ORDER BY t.`topic_type` DESC, t.`topic_bumped` DESC
');
define('MSZ_TOPIC_LISTING_QUERY_PAGINATED', MSZ_TOPIC_LISTING_QUERY_STANDARD . ' LIMIT :offset, :take');

function forum_topic_listing(int $forumId, int $userId, int $offset = 0, int $take = 0): array
{
    $hasPagination = $offset >= 0 && $take > 0;
    $getTopics = Database::connection()->prepare(
        $hasPagination
        ? MSZ_TOPIC_LISTING_QUERY_PAGINATED
        : MSZ_TOPIC_LISTING_QUERY_STANDARD
    );
    $getTopics->bindValue('forum_id', $forumId);
    $getTopics->bindValue('user_id', $userId);

    if ($hasPagination) {
        $getTopics->bindValue('offset', $offset);
        $getTopics->bindValue('take', $take);
    }

    return $getTopics->execute() ? $getTopics->fetchAll() : [];
}
