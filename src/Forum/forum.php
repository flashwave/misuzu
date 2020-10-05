<?php
function forum_get_parent_id(int $forumId): int {
    if($forumId < 1) {
        return 0;
    }

    static $memoized = [];

    if(array_key_exists($forumId, $memoized)) {
        return $memoized[$forumId];
    }

    $getParent = \Misuzu\DB::prepare('
        SELECT `forum_parent`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');
    $getParent->bind('forum_id', $forumId);

    return (int)$getParent->fetchColumn();
}

function forum_get_child_ids(int $forumId): array {
    if($forumId < 1) {
        return [];
    }

    static $memoized = [];

    if(array_key_exists($forumId, $memoized)) {
        return $memoized[$forumId];
    }

    $getChildren = \Misuzu\DB::prepare('
        SELECT `forum_id`
        FROM `msz_forum_categories`
        WHERE `forum_parent` = :forum_id
    ');
    $getChildren->bind('forum_id', $forumId);
    $children = $getChildren->fetchAll();

    return $memoized[$forumId] = array_column($children, 'forum_id');
}

function forum_topics_unread(int $forumId, int $userId): int {
    if($userId < 1 || $forumId < 1) {
        return false;
    }

    static $memoized = [];
    $memoId = "{$forumId}-{$userId}";

    if(array_key_exists($memoId, $memoized)) {
        return $memoized[$memoId];
    }

    $memoized[$memoId] = 0;
    $children = forum_get_child_ids($forumId);

    foreach($children as $child) {
        $memoized[$memoId] += forum_topics_unread($child, $userId);
    }

    if(forum_perms_check_user(MSZ_FORUM_PERMS_GENERAL, $forumId, $userId, MSZ_FORUM_PERM_SET_READ)) {
        $countUnread = \Misuzu\DB::prepare('
            SELECT COUNT(ti.`topic_id`)
            FROM `msz_forum_topics` AS ti
            LEFT JOIN `msz_forum_topics_track` AS tt
            ON tt.`topic_id` = ti.`topic_id` AND tt.`user_id` = :user_id
            WHERE ti.`forum_id` = :forum_id
            AND ti.`topic_deleted` IS NULL
            AND ti.`topic_bumped` >= NOW() - INTERVAL 1 MONTH
            AND (
                tt.`track_last_read` IS NULL
                OR tt.`track_last_read` < ti.`topic_bumped`
            )
        ');
        $countUnread->bind('forum_id', $forumId);
        $countUnread->bind('user_id', $userId);
        $memoized[$memoId] += (int)$countUnread->fetchColumn();
    }

    return $memoized[$memoId];
}

function forum_timeout(int $forumId, int $userId): int {
    $checkTimeout = \Misuzu\DB::prepare('
        SELECT TIMESTAMPDIFF(SECOND, COALESCE(MAX(`post_created`), NOW() - INTERVAL 1 YEAR), NOW())
        FROM `msz_forum_posts`
        WHERE `forum_id` = :forum_id
        AND `user_id` = :user_id
    ');
    $checkTimeout->bind('forum_id', $forumId);
    $checkTimeout->bind('user_id', $userId);

    return (int)$checkTimeout->fetchColumn();
}

// $forumId == null marks all forums as read
function forum_mark_read(?int $forumId, int $userId): void {
    if(($forumId !== null && $forumId < 1) || $userId < 1) {
        return;
    }

    $entireForum = $forumId === null;

    if(!$entireForum) {
        $children = forum_get_child_ids($forumId);

        foreach($children as $child) {
            forum_mark_read($child, $userId);
        }
    }

    $doMark = \Misuzu\DB::prepare(sprintf(
        '
            INSERT INTO `msz_forum_topics_track`
                (`user_id`, `topic_id`, `forum_id`, `track_last_read`)
            SELECT u.`user_id`, t.`topic_id`, t.`forum_id`, NOW()
            FROM `msz_forum_topics` AS t
            LEFT JOIN `msz_users` AS u
            ON u.`user_id` = :user
            WHERE t.`topic_deleted` IS NULL
            AND t.`topic_bumped` >= NOW() - INTERVAL 1 MONTH
            %1$s
            GROUP BY t.`topic_id`
            ON DUPLICATE KEY UPDATE
                `track_last_read` = NOW()
        ',
        $entireForum ? '' : 'AND t.`forum_id` = :forum'
    ));
    $doMark->bind('user', $userId);

    if(!$entireForum) {
        $doMark->bind('forum', $forumId);
    }

    $doMark->execute();
}

function forum_count_synchronise(int $forumId = \Misuzu\Forum\ForumCategory::ROOT_ID, bool $save = true): array {
    try {
        return \Misuzu\Forum\ForumCategory::byId($forumId)->synchronise($save);
    } catch(\Misuzu\Forum\ForumCategoryNotFoundException $ex) {
        return ['topics' => 0, 'posts' => 0];
    }
}
