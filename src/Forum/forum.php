<?php
/**********************
 * GLOBAL PERMISSIONS *
 **********************/
define('MSZ_PERM_FORUM_MANAGE_FORUMS', 1);
define('MSZ_PERM_FORUM_VIEW_LEADERBOARD', 2);

/*************************
 * PER-FORUM PERMISSIONS *
 *************************/
define('MSZ_FORUM_PERM_LIST_FORUM', 1); // can see stats, but will get error when trying to view
define('MSZ_FORUM_PERM_VIEW_FORUM', 1 << 1);

define('MSZ_FORUM_PERM_CREATE_TOPIC', 1 << 10);
//define('MSZ_FORUM_PERM_DELETE_TOPIC', 1 << 11); // use MSZ_FORUM_PERM_DELETE_ANY_POST instead
define('MSZ_FORUM_PERM_MOVE_TOPIC', 1 << 12);
define('MSZ_FORUM_PERM_LOCK_TOPIC', 1 << 13);
define('MSZ_FORUM_PERM_STICKY_TOPIC', 1 << 14);
define('MSZ_FORUM_PERM_ANNOUNCE_TOPIC', 1 << 15);
define('MSZ_FORUM_PERM_GLOBAL_ANNOUNCE_TOPIC', 1 << 16);
define('MSZ_FORUM_PERM_BUMP_TOPIC', 1 << 17);
define('MSZ_FORUM_PERM_PRIORITY_VOTE', 1 << 18);

define('MSZ_FORUM_PERM_CREATE_POST', 1 << 20);
define('MSZ_FORUM_PERM_EDIT_POST', 1 << 21);
define('MSZ_FORUM_PERM_EDIT_ANY_POST', 1 << 22);
define('MSZ_FORUM_PERM_DELETE_POST', 1 << 23);
define('MSZ_FORUM_PERM_DELETE_ANY_POST', 1 << 24);

// shorthands, never use these to SET!!!!!!!
define('MSZ_FORUM_PERM_SET_READ', MSZ_FORUM_PERM_LIST_FORUM | MSZ_FORUM_PERM_VIEW_FORUM);
define(
    'MSZ_FORUM_PERM_SET_WRITE',
    MSZ_FORUM_PERM_CREATE_TOPIC
    | MSZ_FORUM_PERM_MOVE_TOPIC
    | MSZ_FORUM_PERM_LOCK_TOPIC
    | MSZ_FORUM_PERM_STICKY_TOPIC
    | MSZ_FORUM_PERM_ANNOUNCE_TOPIC
    | MSZ_FORUM_PERM_GLOBAL_ANNOUNCE_TOPIC
    | MSZ_FORUM_PERM_CREATE_POST
    | MSZ_FORUM_PERM_EDIT_POST
    | MSZ_FORUM_PERM_EDIT_ANY_POST
    | MSZ_FORUM_PERM_DELETE_POST
    | MSZ_FORUM_PERM_DELETE_ANY_POST
    | MSZ_FORUM_PERM_BUMP_TOPIC
    | MSZ_FORUM_PERM_PRIORITY_VOTE
);

define('MSZ_FORUM_TYPE_DISCUSSION', 0);
define('MSZ_FORUM_TYPE_CATEGORY', 1);
define('MSZ_FORUM_TYPE_LINK', 2);
define('MSZ_FORUM_TYPE_FEATURE', 3);
define('MSZ_FORUM_TYPES', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_CATEGORY,
    MSZ_FORUM_TYPE_LINK,
    MSZ_FORUM_TYPE_FEATURE,
]);

define('MSZ_FORUM_MAY_HAVE_CHILDREN', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_CATEGORY,
    MSZ_FORUM_TYPE_FEATURE,
]);

define('MSZ_FORUM_MAY_HAVE_TOPICS', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_FEATURE,
]);

define('MSZ_FORUM_HAS_PRIORITY_VOTING', [
    MSZ_FORUM_TYPE_FEATURE,
]);

define('MSZ_FORUM_ROOT', 0);
define('MSZ_FORUM_ROOT_DATA', [ // should be compatible with the data fetched in forum_get_root_categories
    'forum_id' => MSZ_FORUM_ROOT,
    'forum_name' => 'Forums',
    'forum_children' => 0,
    'forum_type' => MSZ_FORUM_TYPE_CATEGORY,
    'forum_colour' => null,
    'forum_permissions' => MSZ_FORUM_PERM_SET_READ,
]);

function forum_is_valid_type(int $type): bool {
    return in_array($type, MSZ_FORUM_TYPES, true);
}

function forum_may_have_children(int $forumType): bool {
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_CHILDREN);
}

function forum_may_have_topics(int $forumType): bool {
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_TOPICS);
}

function forum_has_priority_voting(int $forumType): bool {
    return in_array($forumType, MSZ_FORUM_HAS_PRIORITY_VOTING);
}

function forum_get(int $forumId, bool $showDeleted = false): array {
    $getForum = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                `forum_id`, `forum_name`, `forum_type`, `forum_link`, `forum_archived`,
                `forum_link_clicks`, `forum_parent`, `forum_colour`, `forum_icon`,
                (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics`
                    WHERE `forum_id` = f.`forum_id`
                    %1$s
                ) as `forum_topic_count`
            FROM `msz_forum_categories` as f
            WHERE `forum_id` = :forum_id
        ',
        $showDeleted ? '' : 'AND `topic_deleted` IS NULL'
    ));
    $getForum->bind('forum_id', $forumId);
    return $getForum->fetch();
}

function forum_get_root_categories(int $userId): array {
    $getCategories = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                f.`forum_id`, f.`forum_name`, f.`forum_type`, f.`forum_colour`, f.`forum_icon`,
                (
                    SELECT COUNT(`forum_id`)
                    FROM `msz_forum_categories` AS sf
                    WHERE sf.`forum_parent` = f.`forum_id`
                ) AS `forum_children`
            FROM `msz_forum_categories` AS f
            WHERE f.`forum_parent` = 0
            AND f.`forum_type` = %1$d
            AND f.`forum_hidden` = 0
            GROUP BY f.`forum_id`
            ORDER BY f.`forum_order`
        ',
        MSZ_FORUM_TYPE_CATEGORY
    ));
    $categories = array_merge([MSZ_FORUM_ROOT_DATA], $getCategories->fetchAll());

    $getRootForumCount = \Misuzu\DB::prepare(sprintf(
        "
            SELECT COUNT(`forum_id`)
            FROM `msz_forum_categories`
            WHERE `forum_parent` = %d
            AND `forum_type` != %d
        ",
        MSZ_FORUM_ROOT,
        MSZ_FORUM_TYPE_CATEGORY
    ));
    $categories[0]['forum_children'] = (int)$getRootForumCount->fetchColumn();

    foreach($categories as $key => $category) {
        $categories[$key]['forum_permissions'] = $perms = forum_perms_get_user($category['forum_id'], $userId)[MSZ_FORUM_PERMS_GENERAL];

        if(!perms_check($perms, MSZ_FORUM_PERM_SET_READ)) {
            unset($categories[$key]);
            continue;
        }

        $categories[$key] = array_merge(
            $category,
            ['forum_unread' => forum_topics_unread($category['forum_id'], $userId)],
            forum_latest_post($category['forum_id'], $userId)
        );
    }

    return $categories;
}

function forum_get_breadcrumbs(
    int $forumId,
    string $linkFormat = '/forum/forum.php?f=%d',
    string $rootFormat = '/forum/#f%d',
    array $indexLink = ['Forums' => '/forum/']
): array {
    $breadcrumbs = [];
    $getBreadcrumb = \Misuzu\DB::prepare('
        SELECT `forum_id`, `forum_name`, `forum_type`, `forum_parent`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');

    while($forumId > 0) {
        $getBreadcrumb->bind('forum_id', $forumId);
        $breadcrumb = $getBreadcrumb->fetch();

        if(empty($breadcrumb)) {
            break;
        }

        $breadcrumbs[$breadcrumb['forum_name']] = sprintf(
            $breadcrumb['forum_parent'] === MSZ_FORUM_ROOT
            && $breadcrumb['forum_type'] === MSZ_FORUM_TYPE_CATEGORY
                ? $rootFormat
                : $linkFormat,
            $breadcrumb['forum_id']
        );
        $forumId = $breadcrumb['forum_parent'];
    }

    return array_reverse($breadcrumbs + $indexLink);
}

function forum_get_colour(int $forumId): int {
    $getColours = \Misuzu\DB::prepare('
        SELECT `forum_id`, `forum_parent`, `forum_colour`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');

    while($forumId > 0) {
        $getColours->bind('forum_id', $forumId);
        $colourInfo = $getColours->fetch();

        if(empty($colourInfo)) {
            break;
        }

        if(!empty($colourInfo['forum_colour'])) {
            return $colourInfo['forum_colour'];
        }

        $forumId = $colourInfo['forum_parent'];
    }

    return 0x40000000;
}

function forum_increment_clicks(int $forumId): void {
    $incrementLinkClicks = \Misuzu\DB::prepare(sprintf('
        UPDATE `msz_forum_categories`
        SET `forum_link_clicks` = `forum_link_clicks` + 1
        WHERE `forum_id` = :forum_id
        AND `forum_type` = %d
        AND `forum_link_clicks` IS NOT NULL
    ', MSZ_FORUM_TYPE_LINK));
    $incrementLinkClicks->bind('forum_id', $forumId);
    $incrementLinkClicks->execute();
}

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

function forum_latest_post(int $forumId, int $userId): array {
    if($forumId < 1) {
        return [];
    }

    static $memoized = [];
    $memoId = "{$forumId}-{$userId}";

    if(array_key_exists($memoId, $memoized)) {
        return $memoized[$memoId];
    }

    if(!forum_perms_check_user(MSZ_FORUM_PERMS_GENERAL, $forumId, $userId, MSZ_FORUM_PERM_SET_READ)) {
        return $memoized[$memoId] = [];
    }

    $getLastPost = \Misuzu\DB::prepare('
        SELECT
            p.`post_id` AS `recent_post_id`, t.`topic_id` AS `recent_topic_id`,
            t.`topic_title` AS `recent_topic_title`, t.`topic_bumped` AS `recent_topic_bumped`,
            p.`post_created` AS `recent_post_created`,
            u.`user_id` AS `recent_post_user_id`,
            u.`username` AS `recent_post_username`,
            COALESCE(u.`user_colour`, r.`role_colour`) AS `recent_post_user_colour`,
            UNIX_TIMESTAMP(p.`post_created`) AS `post_created_unix`
        FROM `msz_forum_posts` AS p
        LEFT JOIN `msz_forum_topics` AS t
        ON t.`topic_id` = p.`topic_id`
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
        WHERE p.`forum_id` = :forum_id
        AND p.`post_deleted` IS NULL
        ORDER BY p.`post_id` DESC
    ');
    $getLastPost->bind('forum_id', $forumId);
    $currentLast = $getLastPost->fetch();

    $children = forum_get_child_ids($forumId);

    foreach($children as $child) {
        $lastPost = forum_latest_post($child, $userId);

        if(($currentLast['post_created_unix'] ?? 0) < ($lastPost['post_created_unix'] ?? 0)) {
            $currentLast = $lastPost;
        }
    }

    return $memoized[$memoId] = $currentLast;
}

function forum_get_children(int $parentId, int $userId): array {
    $getListing = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                :user_id AS `target_user_id`,
                f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`, f.`forum_icon`,
                f.`forum_link`, f.`forum_link_clicks`, f.`forum_archived`, f.`forum_colour`,
                f.`forum_count_topics`, f.`forum_count_posts`
            FROM `msz_forum_categories` AS f
            WHERE f.`forum_parent` = :parent_id
            AND f.`forum_hidden` = 0
            AND (
                (f.`forum_parent` = %1$d AND f.`forum_type` != %2$d)
                OR f.`forum_parent` != %1$d
            )
            GROUP BY f.`forum_id`
            ORDER BY f.`forum_order`
        ',
        MSZ_FORUM_ROOT,
        MSZ_FORUM_TYPE_CATEGORY
    ));

    $getListing->bind('user_id', $userId);
    $getListing->bind('parent_id', $parentId);

    $listing = $getListing->fetchAll();

    foreach($listing as $key => $forum) {
        $listing[$key]['forum_permissions'] = $perms = forum_perms_get_user($forum['forum_id'], $userId)[MSZ_FORUM_PERMS_GENERAL];

        if(!perms_check($perms, MSZ_FORUM_PERM_SET_READ)) {
            unset($listing[$key]);
            continue;
        }

        $listing[$key] = array_merge(
            $forum,
            ['forum_unread' => forum_topics_unread($forum['forum_id'], $userId)],
            forum_latest_post($forum['forum_id'], $userId)
        );
    }

    return $listing;
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

function forum_posting_info(int $userId): array {
    $getPostingInfo = \Misuzu\DB::prepare('
        SELECT
            u.`user_country`, u.`user_created`,
            (
                SELECT COUNT(`post_id`)
                FROM `msz_forum_posts`
                WHERE `user_id` = u.`user_id`
                AND `post_deleted` IS NULL
            ) AS `user_forum_posts`,
            (
                SELECT `post_parse`
                FROM `msz_forum_posts`
                WHERE `user_id` = u.`user_id`
                AND `post_deleted` IS NULL
                ORDER BY `post_id` DESC
                LIMIT 1
            ) AS `user_post_parse`
        FROM `msz_users` as u
        WHERE `user_id` = :user_id
    ');
    $getPostingInfo->bind('user_id', $userId);
    return $getPostingInfo->fetch();
}

function forum_count_increase(int $forumId, bool $topic = false): void {
    $increaseCount = \Misuzu\DB::prepare(sprintf(
        '
            UPDATE `msz_forum_categories`
            SET `forum_count_posts` = `forum_count_posts` + 1
                %s
            WHERE `forum_id` = :forum
        ',
        $topic ? ',`forum_count_topics` = `forum_count_topics` + 1' : ''
    ));
    $increaseCount->bind('forum', $forumId);
    $increaseCount->execute();
}

function forum_count_synchronise(int $forumId = MSZ_FORUM_ROOT, bool $save = true): array {
    static $getChildren = null;
    static $getCounts = null;
    static $setCounts = null;

    if(is_null($getChildren)) {
        $getChildren = \Misuzu\DB::prepare('
            SELECT `forum_id`, `forum_parent`
            FROM `msz_forum_categories`
            WHERE `forum_parent` = :parent
        ');
    }

    if(is_null($getCounts)) {
        $getCounts = \Misuzu\DB::prepare('
            SELECT :forum as `target_forum_id`,
            (
                SELECT COUNT(`topic_id`)
                FROM `msz_forum_topics`
                WHERE `forum_id` = `target_forum_id`
                AND `topic_deleted` IS NULL
            ) AS `count_topics`,
            (
                SELECT COUNT(`post_id`)
                FROM `msz_forum_posts`
                WHERE `forum_id` = `target_forum_id`
                AND `post_deleted` IS NULL
            ) AS `count_posts`
        ');
    }

    if($save && is_null($setCounts)) {
        $setCounts = \Misuzu\DB::prepare('
            UPDATE `msz_forum_categories`
            SET `forum_count_topics` = :topics,
                `forum_count_posts` = :posts
            WHERE `forum_id` = :forum_id
        ');
    }

    $getChildren->bind('parent', $forumId);
    $children = $getChildren->fetchAll();

    $topics = 0;
    $posts = 0;

    foreach($children as $child) {
        $childCount = forum_count_synchronise($child['forum_id'], $save);
        $topics += $childCount['topics'];
        $posts += $childCount['posts'];
    }

    $getCounts->bind('forum', $forumId);
    $counts = $getCounts->fetch();
    $topics += $counts['count_topics'];
    $posts += $counts['count_posts'];

    if($forumId > 0 && $save) {
        $setCounts->bind('forum_id', $forumId);
        $setCounts->bind('topics', $topics);
        $setCounts->bind('posts', $posts);
        $setCounts->execute();
    }

    return compact('topics', 'posts');
}
