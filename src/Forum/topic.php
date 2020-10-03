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

function forum_topic_is_valid_type(int $type): bool {
    return in_array($type, MSZ_TOPIC_TYPES, true);
}

function forum_topic_create(
    int $forumId,
    int $userId,
    string $title,
    int $type = MSZ_TOPIC_TYPE_DISCUSSION
): int {
    if(empty($title) || !forum_topic_is_valid_type($type)) {
        return 0;
    }

    $createTopic = \Misuzu\DB::prepare('
        INSERT INTO `msz_forum_topics`
            (`forum_id`, `user_id`, `topic_title`, `topic_type`)
        VALUES
            (:forum_id, :user_id, :topic_title, :topic_type)
    ');
    $createTopic->bind('forum_id', $forumId);
    $createTopic->bind('user_id', $userId);
    $createTopic->bind('topic_title', $title);
    $createTopic->bind('topic_type', $type);

    return $createTopic->execute() ? \Misuzu\DB::lastId() : 0;
}

function forum_topic_update(int $topicId, ?string $title, ?int $type = null): bool {
    if($topicId < 1) {
        return false;
    }

    // make sure it's null and not some other kinda empty
    if(empty($title)) {
        $title = null;
    }

    if($type !== null && !forum_topic_is_valid_type($type)) {
        return false;
    }

    $updateTopic = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_title` = COALESCE(:topic_title, `topic_title`),
            `topic_type` = COALESCE(:topic_type, `topic_type`)
        WHERE `topic_id` = :topic_id
    ');
    $updateTopic->bind('topic_id', $topicId);
    $updateTopic->bind('topic_title', $title);
    $updateTopic->bind('topic_type', $type);

    return $updateTopic->execute();
}

function forum_topic_get(int $topicId, bool $allowDeleted = false): array {
    $getTopic = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                t.`topic_id`, t.`forum_id`, t.`topic_title`, t.`topic_type`, t.`topic_locked`, t.`topic_created`,
                f.`forum_archived` AS `topic_archived`, t.`topic_deleted`, t.`topic_bumped`, f.`forum_type`,
                fp.`topic_id` AS `author_post_id`, fp.`user_id` AS `author_user_id`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `post_deleted` IS NULL
                ) AS `topic_count_posts`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `post_deleted` IS NOT NULL
                ) AS `topic_count_posts_deleted`
            FROM `msz_forum_topics` AS t
            LEFT JOIN `msz_forum_categories` AS f
            ON f.`forum_id` = t.`forum_id`
            LEFT JOIN `msz_forum_posts` AS fp
            ON fp.`post_id` = (
                SELECT MIN(`post_id`)
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
            )
            WHERE t.`topic_id` = :topic_id
            %s
        ',
        $allowDeleted ? '' : 'AND t.`topic_deleted` IS NULL'
    ));
    $getTopic->bind('topic_id', $topicId);
    return $getTopic->fetch();
}

function forum_topic_bump(int $topicId): bool {
    $bumpTopic = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_bumped` = NOW()
        WHERE `topic_id` = :topic_id
        AND `topic_deleted` IS NULL
    ');
    $bumpTopic->bind('topic_id', $topicId);
    return $bumpTopic->execute();
}

function forum_topic_views_increment(int $topicId): void {
    if($topicId < 1) {
        return;
    }

    $bumpViews = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_count_views` = `topic_count_views` + 1
        WHERE `topic_id` = :topic_id
    ');
    $bumpViews->bind('topic_id', $topicId);
    $bumpViews->execute();
}

function forum_topic_mark_read(int $userId, int $topicId, int $forumId): void {
    if($userId < 1) {
        return;
    }

    // previously a TRIGGER was used to achieve this behaviour,
    // but those explode when running on a lot of queries (like forum_mark_read() does)
    // so instead we get to live with this garbage now
    try {
        $markAsRead = \Misuzu\DB::prepare('
            INSERT INTO `msz_forum_topics_track`
                (`user_id`, `topic_id`, `forum_id`, `track_last_read`)
            VALUES
                (:user_id, :topic_id, :forum_id, NOW())
        ');
        $markAsRead->bind('user_id', $userId);
        $markAsRead->bind('topic_id', $topicId);
        $markAsRead->bind('forum_id', $forumId);

        if($markAsRead->execute()) {
            forum_topic_views_increment($topicId);
        }
    } catch(PDOException $ex) {
        if($ex->getCode() != '23000') {
            throw $ex;
        }

        $markAsRead = \Misuzu\DB::prepare('
            UPDATE `msz_forum_topics_track`
            SET `track_last_read` = NOW(),
                `forum_id` = :forum_id
            WHERE `user_id` = :user_id
            AND `topic_id` = :topic_id
        ');
        $markAsRead->bind('user_id', $userId);
        $markAsRead->bind('topic_id', $topicId);
        $markAsRead->bind('forum_id', $forumId);
        $markAsRead->execute();
    }
}

function forum_topic_listing(
    int $forumId,               int $userId,
    int $offset = 0,            int $take = 0,
    bool $showDeleted = false,  bool $sortByPriority = false
): array {
    $hasPagination = $offset >= 0 && $take > 0;
    $getTopics = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                :user_id AS `target_user_id`,
                t.`topic_id`, t.`topic_title`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
                t.`topic_bumped`, t.`topic_deleted`, t.`topic_count_views`, f.`forum_type`,
                COALESCE(SUM(tp.`topic_priority`), 0) AS `topic_priority`,
                au.`user_id` AS `author_id`, au.`username` AS `author_name`,
                COALESCE(au.`user_colour`, ar.`role_colour`) AS `author_colour`,
                lp.`post_id` AS `response_id`,
                lp.`post_created` AS `response_created`,
                lu.`user_id` AS `respondent_id`,
                lu.`username` AS `respondent_name`,
                COALESCE(lu.`user_colour`, lr.`role_colour`) AS `respondent_colour`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    %5$s
                ) AS `topic_count_posts`,
                (
                    SELECT CEIL(COUNT(`post_id`) / %6$d)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    %5$s
                ) AS `topic_pages`,
                (
                    SELECT
                        `target_user_id` > 0
                    AND
                        t.`topic_bumped` > NOW() - INTERVAL 1 MONTH
                    AND (
                        SELECT COUNT(ti.`topic_id`) < 1
                        FROM `msz_forum_topics_track` AS tt
                        RIGHT JOIN `msz_forum_topics` AS ti
                        ON ti.`topic_id` = tt.`topic_id`
                        WHERE ti.`topic_id` = t.`topic_id`
                        AND tt.`user_id` = `target_user_id`
                        AND `track_last_read` >= `topic_bumped`
                    )
                ) AS `topic_unread`,
                (
                    SELECT COUNT(`post_id`) > 0
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `user_id` = `target_user_id`
                    LIMIT 1
                ) AS `topic_participated`
            FROM `msz_forum_topics` AS t
            LEFT JOIN `msz_forum_topics_priority` AS tp
            ON tp.`topic_id` = t.`topic_id`
            LEFT JOIN `msz_forum_categories` AS f
            ON f.`forum_id` = t.`forum_id`
            LEFT JOIN `msz_users` AS au
            ON t.`user_id` = au.`user_id`
            LEFT JOIN `msz_roles` AS ar
            ON ar.`role_id` = au.`display_role`
            LEFT JOIN `msz_forum_posts` AS lp
            ON lp.`post_id` = (
                SELECT `post_id`
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
                %5$s
                ORDER BY `post_id` DESC
                LIMIT 1
            )
            LEFT JOIN `msz_users` AS lu
            ON lu.`user_id` = lp.`user_id`
            LEFT JOIN `msz_roles` AS lr
            ON lr.`role_id` = lu.`display_role`
            WHERE (
                t.`forum_id` = :forum_id
                OR t.`topic_type` = %3$d
            )
            %1$s
            GROUP BY t.`topic_id`
            ORDER BY FIELD(t.`topic_type`, %4$s) DESC, %7$s t.`topic_bumped` DESC
            %2$s
        ',
        $showDeleted ? '' : 'AND t.`topic_deleted` IS NULL',
        $hasPagination ? 'LIMIT :offset, :take' : '',
        MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT,
        implode(',', array_reverse(MSZ_TOPIC_TYPE_ORDER)),
        $showDeleted ? '' : 'AND `post_deleted` IS NULL',
        MSZ_FORUM_POSTS_PER_PAGE,
        $sortByPriority ? '`topic_priority` DESC,' : ''
    ));
    $getTopics->bind('forum_id', $forumId);
    $getTopics->bind('user_id', $userId);

    if($hasPagination) {
        $getTopics->bind('offset', $offset);
        $getTopics->bind('take', $take);
    }

    return $getTopics->fetchAll();
}

function forum_topic_count_user(int $authorId, int $userId, bool $showDeleted = false): int {
    $getTopics = \Misuzu\DB::prepare(sprintf(
        '
            SELECT COUNT(`topic_id`)
            FROM `msz_forum_topics` AS t
            WHERE t.`user_id` = :author_id
            %1$s
        ',
        $showDeleted ? '' : 'AND t.`topic_deleted` IS NULL'
    ));
    $getTopics->bind('author_id', $authorId);
    //$getTopics->bind('user_id', $userId);

    return (int)$getTopics->fetchColumn();
}

// Remove unneccesary stuff from the sql stmt
function forum_topic_listing_user(
    int $authorId,
    int $userId,
    int $offset = 0,
    int $take = 0,
    bool $showDeleted = false
): array {
    $hasPagination = $offset >= 0 && $take > 0;
    $getTopics = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                :user_id AS `target_user_id`,
                t.`topic_id`, t.`topic_title`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
                t.`topic_bumped`, t.`topic_deleted`, t.`topic_count_views`, f.`forum_type`,
                au.`user_id` AS `author_id`, au.`username` AS `author_name`,
                COALESCE(au.`user_colour`, ar.`role_colour`) AS `author_colour`,
                lp.`post_id` AS `response_id`,
                lp.`post_created` AS `response_created`,
                lu.`user_id` AS `respondent_id`,
                lu.`username` AS `respondent_name`,
                COALESCE(lu.`user_colour`, lr.`role_colour`) AS `respondent_colour`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    %5$s
                ) AS `topic_count_posts`,
                (
                    SELECT CEIL(COUNT(`post_id`) / %6$d)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    %5$s
                ) AS `topic_pages`,
                (
                    SELECT
                        `target_user_id` > 0
                    AND
                        t.`topic_bumped` > NOW() - INTERVAL 1 MONTH
                    AND (
                        SELECT COUNT(ti.`topic_id`) < 1
                        FROM `msz_forum_topics_track` AS tt
                        RIGHT JOIN `msz_forum_topics` AS ti
                        ON ti.`topic_id` = tt.`topic_id`
                        WHERE ti.`topic_id` = t.`topic_id`
                        AND tt.`user_id` = `target_user_id`
                        AND `track_last_read` >= `topic_bumped`
                    )
                ) AS `topic_unread`,
                (
                    SELECT COUNT(`post_id`) > 0
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `user_id` = `target_user_id`
                    LIMIT 1
                ) AS `topic_participated`
            FROM `msz_forum_topics` AS t
            LEFT JOIN `msz_forum_categories` AS f
            ON f.`forum_id` = t.`forum_id`
            LEFT JOIN `msz_users` AS au
            ON t.`user_id` = au.`user_id`
            LEFT JOIN `msz_roles` AS ar
            ON ar.`role_id` = au.`display_role`
            LEFT JOIN `msz_forum_posts` AS lp
            ON lp.`post_id` = (
                SELECT `post_id`
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
                %5$s
                ORDER BY `post_id` DESC
                LIMIT 1
            )
            LEFT JOIN `msz_users` AS lu
            ON lu.`user_id` = lp.`user_id`
            LEFT JOIN `msz_roles` AS lr
            ON lr.`role_id` = lu.`display_role`
            WHERE au.`user_id` = :author_id
            %1$s
            ORDER BY FIELD(t.`topic_type`, %4$s) DESC, t.`topic_bumped` DESC
            %2$s
        ',
        $showDeleted ? '' : 'AND t.`topic_deleted` IS NULL',
        $hasPagination ? 'LIMIT :offset, :take' : '',
        MSZ_TOPIC_TYPE_GLOBAL_ANNOUNCEMENT,
        implode(',', array_reverse(MSZ_TOPIC_TYPE_ORDER)),
        $showDeleted ? '' : 'AND `post_deleted` IS NULL',
        MSZ_FORUM_POSTS_PER_PAGE
    ));
    $getTopics->bind('author_id', $authorId);
    $getTopics->bind('user_id', $userId);

    if($hasPagination) {
        $getTopics->bind('offset', $offset);
        $getTopics->bind('take', $take);
    }

    return $getTopics->fetchAll();
}

function forum_topic_listing_search(string $query, int $userId): array {
    $getTopics = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                :user_id AS `target_user_id`,
                t.`topic_id`, t.`topic_title`, t.`topic_locked`, t.`topic_type`, t.`topic_created`,
                t.`topic_bumped`, t.`topic_deleted`, t.`topic_count_views`, f.`forum_type`,
                au.`user_id` AS `author_id`, au.`username` AS `author_name`,
                COALESCE(au.`user_colour`, ar.`role_colour`) AS `author_colour`,
                lp.`post_id` AS `response_id`,
                lp.`post_created` AS `response_created`,
                lu.`user_id` AS `respondent_id`,
                lu.`username` AS `respondent_name`,
                COALESCE(lu.`user_colour`, lr.`role_colour`) AS `respondent_colour`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `post_deleted` IS NULL
                ) AS `topic_count_posts`,
                (
                    SELECT CEIL(COUNT(`post_id`) / %2$d)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `post_deleted` IS NULL
                ) AS `topic_pages`,
                (
                    SELECT
                        `target_user_id` > 0
                    AND
                        t.`topic_bumped` > NOW() - INTERVAL 1 MONTH
                    AND (
                        SELECT COUNT(ti.`topic_id`) < 1
                        FROM `msz_forum_topics_track` AS tt
                        RIGHT JOIN `msz_forum_topics` AS ti
                        ON ti.`topic_id` = tt.`topic_id`
                        WHERE ti.`topic_id` = t.`topic_id`
                        AND tt.`user_id` = `target_user_id`
                        AND `track_last_read` >= `topic_bumped`
                    )
                ) AS `topic_unread`,
                (
                    SELECT COUNT(`post_id`) > 0
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = t.`topic_id`
                    AND `user_id` = `target_user_id`
                    LIMIT 1
                ) AS `topic_participated`
            FROM `msz_forum_topics` AS t
            LEFT JOIN `msz_forum_categories` AS f
            ON f.`forum_id` = t.`forum_id`
            LEFT JOIN `msz_users` AS au
            ON t.`user_id` = au.`user_id`
            LEFT JOIN `msz_roles` AS ar
            ON ar.`role_id` = au.`display_role`
            LEFT JOIN `msz_forum_posts` AS lp
            ON lp.`post_id` = (
                SELECT `post_id`
                FROM `msz_forum_posts`
                WHERE `topic_id` = t.`topic_id`
                AND `post_deleted` IS NULL
                ORDER BY `post_id` DESC
                LIMIT 1
            )
            LEFT JOIN `msz_users` AS lu
            ON lu.`user_id` = lp.`user_id`
            LEFT JOIN `msz_roles` AS lr
            ON lr.`role_id` = lu.`display_role`
            WHERE MATCH(`topic_title`)
            AGAINST (:query IN NATURAL LANGUAGE MODE)
            AND t.`topic_deleted` IS NULL
            ORDER BY FIELD(t.`topic_type`, %1$s) DESC, t.`topic_bumped` DESC
        ',
        implode(',', array_reverse(MSZ_TOPIC_TYPE_ORDER)),
        MSZ_FORUM_POSTS_PER_PAGE
    ));
    $getTopics->bind('query', $query);
    $getTopics->bind('user_id', $userId);

    return $getTopics->fetchAll();
}

function forum_topic_lock(int $topicId): bool {
    if($topicId < 1) {
        return false;
    }

    $markLocked = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_locked` = NOW()
        WHERE `topic_id` = :topic
        AND `topic_locked` IS NULL
    ');
    $markLocked->bind('topic', $topicId);

    return $markLocked->execute();
}

function forum_topic_unlock(int $topicId): bool {
    if($topicId < 1) {
        return false;
    }

    $markUnlocked = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_locked` = NULL
        WHERE `topic_id` = :topic
        AND `topic_locked` IS NOT NULL
    ');
    $markUnlocked->bind('topic', $topicId);

    return $markUnlocked->execute();
}

define('MSZ_E_FORUM_TOPIC_DELETE_OK', 0);       // deleting is fine
define('MSZ_E_FORUM_TOPIC_DELETE_USER', 1);     // invalid user
define('MSZ_E_FORUM_TOPIC_DELETE_TOPIC', 2);    // topic doesn't exist
define('MSZ_E_FORUM_TOPIC_DELETE_DELETED', 3);  // topic is already marked as deleted
define('MSZ_E_FORUM_TOPIC_DELETE_OWNER', 4);    // you may only delete your own topics
define('MSZ_E_FORUM_TOPIC_DELETE_OLD', 5);      // topic has existed for too long to be deleted
define('MSZ_E_FORUM_TOPIC_DELETE_PERM', 6);     // you aren't allowed to delete topics
define('MSZ_E_FORUM_TOPIC_DELETE_POSTS', 7);    // the topic already has replies

// only allow topics made within a day of posting to be deleted by normal users
define('MSZ_FORUM_TOPIC_DELETE_TIME_LIMIT', 60 * 60 * 24);

// only allow topics with a single post to be deleted, includes soft deleted posts
define('MSZ_FORUM_TOPIC_DELETE_POST_LIMIT', 1);

// set $userId to null for system request, make sure this is NEVER EVER null on user request
// $topicId can also be a the return value of forum_topic_get if you already grabbed it once before
function forum_topic_can_delete($topicId, ?int $userId = null): int {
    if($userId !== null && $userId < 1) {
        return MSZ_E_FORUM_TOPIC_DELETE_USER;
    }

    if($topicId instanceof \Misuzu\Forum\ForumTopic) {
        $topic = [
            'forum_id' => $topicId->getCategoryId(),
            'topic_deleted' => $topicId->isDeleted(),
            'author_user_id' => $topicId->getUserId(),
            'topic_created' => date('c', $topicId->getCreatedTime()),
            'topic_count_posts' => $topicId->getActualPostCount(true),
            'topic_count_posts_deleted' => 0,
        ];
    } elseif(is_array($topicId)) {
        $topic = $topicId;
    } else {
        $topic = forum_topic_get((int)$topicId, true);
    }

    if(empty($topic)) {
        return MSZ_E_FORUM_TOPIC_DELETE_TOPIC;
    }

    $isSystemReq    = $userId === null;
    $perms          = $isSystemReq ? 0      : forum_perms_get_user($topic['forum_id'], $userId)[MSZ_FORUM_PERMS_GENERAL];
    $canDeleteAny   = $isSystemReq ? true   : perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);
    $canViewPost    = $isSystemReq ? true   : perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM);
    $postIsDeleted  = !empty($topic['topic_deleted']);

    if(!$canViewPost) {
        return MSZ_E_FORUM_TOPIC_DELETE_TOPIC;
    }

    if($postIsDeleted) {
        return $canDeleteAny ? MSZ_E_FORUM_TOPIC_DELETE_DELETED : MSZ_E_FORUM_TOPIC_DELETE_TOPIC;
    }

    if($isSystemReq) {
        return MSZ_E_FORUM_TOPIC_DELETE_OK;
    }

    if(!$canDeleteAny) {
        if(!perms_check($perms, MSZ_FORUM_PERM_DELETE_POST)) {
            return MSZ_E_FORUM_TOPIC_DELETE_PERM;
        }

        if($topic['author_user_id'] !== $userId) {
            return MSZ_E_FORUM_TOPIC_DELETE_OWNER;
        }

        if(strtotime($topic['topic_created']) <= time() - MSZ_FORUM_TOPIC_DELETE_TIME_LIMIT) {
            return MSZ_E_FORUM_TOPIC_DELETE_OLD;
        }

        $totalReplies = $topic['topic_count_posts'] + $topic['topic_count_posts_deleted'];

        if($totalReplies > MSZ_E_FORUM_TOPIC_DELETE_POSTS) {
            return MSZ_E_FORUM_TOPIC_DELETE_POSTS;
        }
    }

    return MSZ_E_FORUM_TOPIC_DELETE_OK;
}

function forum_topic_delete(int $topicId): bool {
    if($topicId < 1) {
        return false;
    }

    $markTopicDeleted = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_deleted` = NOW()
        WHERE `topic_id` = :topic
        AND `topic_deleted` IS NULL
    ');
    $markTopicDeleted->bind('topic', $topicId);

    if(!$markTopicDeleted->execute()) {
        return false;
    }

    $markPostsDeleted = \Misuzu\DB::prepare('
        UPDATE `msz_forum_posts` as p
        SET p.`post_deleted` = (
            SELECT `topic_deleted`
            FROM `msz_forum_topics`
            WHERE `topic_id` = p.`topic_id`
        )
        WHERE p.`topic_id` = :topic
        AND p.`post_deleted` IS NULL
    ');
    $markPostsDeleted->bind('topic', $topicId);

    return $markPostsDeleted->execute();
}

function forum_topic_restore(int $topicId): bool {
    if($topicId < 1) {
        return false;
    }

    $markPostsRestored = \Misuzu\DB::prepare('
        UPDATE `msz_forum_posts` as p
        SET p.`post_deleted` = NULL
        WHERE p.`topic_id` = :topic
        AND p.`post_deleted` = (
            SELECT `topic_deleted`
            FROM `msz_forum_topics`
            WHERE `topic_id` = p.`topic_id`
        )
    ');
    $markPostsRestored->bind('topic', $topicId);

    if(!$markPostsRestored->execute()) {
        return false;
    }

    $markTopicRestored = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_deleted` = NULL
        WHERE `topic_id` = :topic
        AND `topic_deleted` IS NOT NULL
    ');
    $markTopicRestored->bind('topic', $topicId);

    return $markTopicRestored->execute();
}

function forum_topic_nuke(int $topicId): bool {
    if($topicId < 1) {
        return false;
    }

    $nukeTopic = \Misuzu\DB::prepare('
        DELETE FROM `msz_forum_topics`
        WHERE `topic_id` = :topic
    ');
    $nukeTopic->bind('topic', $topicId);
    return $nukeTopic->execute();
}
