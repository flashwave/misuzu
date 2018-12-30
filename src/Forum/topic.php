<?php
define('MSZ_TOPIC_TYPE_DISCUSSION', 0);
define('MSZ_TOPIC_TYPE_STICKY', 1);
define('MSZ_TOPIC_TYPE_ANNOUNCEMENT', 2);
define('MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT', 3);
define('MSZ_TOPIC_TYPES', [
    MSZ_TOPIC_TYPE_DISCUSSION,
    MSZ_TOPIC_TYPE_STICKY,
    MSZ_TOPIC_TYPE_ANNOUNCEMENT,
    MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT,
]);

define('MSZ_TOPIC_TYPE_ORDER', [ // in which order to display topics, only add types here that should appear above others
    MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT,
    MSZ_TOPIC_TYPE_ANNOUNCEMENT,
    MSZ_TOPIC_TYPE_STICKY,
]);

function forum_topic_is_valid_type(int $type): bool
{
    return in_array($type, MSZ_TOPIC_TYPES, true);
}

function forum_topic_create(int $forumId, int $userId, string $title, int $type = MSZ_TOPIC_TYPE_DISCUSSION): int
{
    if (empty($title) || !forum_topic_is_valid_type($type)) {
        return 0;
    }

    $createTopic = db_prepare('
        INSERT INTO `msz_forum_topics`
            (`forum_id`, `user_id`, `topic_title`, `topic_type`)
        VALUES
            (:forum_id, :user_id, :topic_title, :topic_type)
    ');
    $createTopic->bindValue('forum_id', $forumId);
    $createTopic->bindValue('user_id', $userId);
    $createTopic->bindValue('topic_title', $title);
    $createTopic->bindValue('topic_type', $type);

    return $createTopic->execute() ? (int)db_last_insert_id() : 0;
}

function forum_topic_update(int $topicId, ?string $title, ?int $type = null): bool
{
    if ($topicId < 1) {
        return false;
    }

    // make sure it's null and not some other kinda empty
    if (empty($title)) {
        $title = null;
    }

    if ($type !== null && !forum_topic_is_valid_type($type)) {
        return false;
    }

    $updateTopic = db_prepare('
        UPDATE `msz_forum_topics`
        SET `topic_title` = COALESCE(:topic_title, `topic_title`),
            `topic_type` = COALESCE(:topic_type, `topic_type`)
        WHERE `topic_id` = :topic_id
    ');
    $updateTopic->bindValue('topic_id', $topicId);
    $updateTopic->bindValue('topic_title', $title);
    $updateTopic->bindValue('topic_type', $type);

    return $updateTopic->execute();
}

function forum_topic_fetch(int $topicId): array
{
    $getTopic = db_prepare('
        SELECT
            t.`topic_id`, t.`forum_id`, t.`topic_title`, t.`topic_type`, t.`topic_locked`, t.`topic_created`,
            f.`forum_archived` as `topic_archived`, t.`topic_deleted`, t.`topic_bumped`,
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
    ');
    $getTopic->bindValue('topic_id', $topicId);
    $topic = $getTopic->execute() ? $getTopic->fetch(PDO::FETCH_ASSOC) : false;
    return $topic ? $topic : [];
}

function forum_topic_bump(int $topicId): bool
{
    $bumpTopic = db_prepare('
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

    $markAsRead = db_prepare('
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

function forum_topic_listing(int $forumId, int $userId, int $offset = 0, int $take = 0, bool $showDeleted = false): array
{
    $hasPagination = $offset >= 0 && $take > 0;
    $getTopics = db_prepare(sprintf(
        '
            SELECT
                :user_id as `target_user_id`,
                t.`topic_id`, t.`topic_title`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
                t.`topic_bumped`, t.`topic_deleted`,
                au.`user_id` as `author_id`, au.`username` as `author_name`,
                COALESCE(au.`user_colour`, ar.`role_colour`) as `author_colour`,
                lp.`post_id` as `response_id`,
                lp.`post_created` as `response_created`,
                lu.`user_id` as `respondent_id`,
                lu.`username` as `respondent_name`,
                COALESCE(lu.`user_colour`, lr.`role_colour`) as `respondent_colour`,
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
            WHERE (
                t.`forum_id` = :forum_id
                OR t.`topic_type` = %3$d
            )
            %1$s
            ORDER BY FIELD(t.`topic_type`, %4$s) DESC, t.`topic_bumped` DESC
            %2$s
        ',
        $showDeleted ? '' : 'AND t.`topic_deleted` IS NULL',
        $hasPagination ? 'LIMIT :offset, :take' : '',
        MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT,
        implode(',', array_reverse(MSZ_TOPIC_TYPE_ORDER))
    ));
    $getTopics->bindValue('forum_id', $forumId);
    $getTopics->bindValue('user_id', $userId);

    if ($hasPagination) {
        $getTopics->bindValue('offset', $offset);
        $getTopics->bindValue('take', $take);
    }

    return $getTopics->execute() ? $getTopics->fetchAll() : [];
}
