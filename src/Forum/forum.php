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
);

define('MSZ_FORUM_TYPE_DISCUSSION', 0);
define('MSZ_FORUM_TYPE_CATEGORY', 1);
define('MSZ_FORUM_TYPE_LINK', 2);
define('MSZ_FORUM_TYPES', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_CATEGORY,
    MSZ_FORUM_TYPE_LINK,
]);

define('MSZ_FORUM_MAY_HAVE_CHILDREN', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_CATEGORY,
]);

define('MSZ_FORUM_MAY_HAVE_TOPICS', [
    MSZ_FORUM_TYPE_DISCUSSION,
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

function forum_is_valid_type(int $type): bool
{
    return in_array($type, MSZ_FORUM_TYPES, true);
}

function forum_may_have_children(int $forumType): bool
{
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_CHILDREN);
}

function forum_may_have_topics(int $forumType): bool
{
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_TOPICS);
}

function forum_get(int $forumId, bool $showDeleted = false): array
{
    $getForum = db_prepare(sprintf(
        '
            SELECT
                `forum_id`, `forum_name`, `forum_type`, `forum_link`, `forum_archived`,
                `forum_link_clicks`, `forum_parent`, `forum_colour`,
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
    $getForum->bindValue('forum_id', $forumId);
    return db_fetch($getForum);
}

function forum_get_root_categories(int $userId): array
{
    $getCategories = db_prepare(sprintf(
        '
            SELECT
                f.`forum_id`, f.`forum_name`, f.`forum_type`, f.`forum_colour`,
                (
                    SELECT COUNT(`forum_id`)
                    FROM `msz_forum_categories` AS sf
                    WHERE sf.`forum_parent` = f.`forum_id`
                ) AS `forum_children`,
                (%2$s) AS `forum_permissions`
            FROM `msz_forum_categories` AS f
            WHERE f.`forum_parent` = 0
            AND f.`forum_type` = %1$d
            AND f.`forum_hidden` = 0
            GROUP BY f.`forum_id`
            HAVING (`forum_permissions` & %3$d) > 0
            ORDER BY f.`forum_order`
        ',
        MSZ_FORUM_TYPE_CATEGORY,
        forum_perms_get_user_sql(MSZ_FORUM_PERMS_GENERAL, 'f.`forum_id`'),
        MSZ_FORUM_PERM_SET_READ
    ));
    $getCategories->bindValue('perm_user_id_user', $userId);
    $getCategories->bindValue('perm_user_id_role', $userId);
    $categories = array_merge([MSZ_FORUM_ROOT_DATA], db_fetch_all($getCategories));

    $getRootForumCount = db_prepare(sprintf(
        "
            SELECT COUNT(`forum_id`)
            FROM `msz_forum_categories`
            WHERE `forum_parent` = %d
            AND `forum_type` != %d
            AND (%s & %d) > 0
        ",
        MSZ_FORUM_ROOT,
        MSZ_FORUM_TYPE_CATEGORY,
        forum_perms_get_user_sql(MSZ_FORUM_PERMS_GENERAL, '`forum_id`'),
        MSZ_FORUM_PERM_SET_READ
    ));
    $getRootForumCount->bindValue('perm_user_id_user', $userId);
    $getRootForumCount->bindValue('perm_user_id_role', $userId);
    $categories[0]['forum_children'] = (int)($getRootForumCount->execute() ? $getRootForumCount->fetchColumn() : 0);

    foreach ($categories as $key => $category) {
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
    $getBreadcrumbs = db_prepare('
        WITH RECURSIVE breadcrumbs(forum_id, forum_name, forum_parent, forum_type) as (
            SELECT c.`forum_id`, c.`forum_name`, c.`forum_parent`, c.`forum_type`
            FROM `msz_forum_categories` as c
            WHERE `forum_id` = :forum_id
            UNION ALL
            SELECT p.`forum_id`, p.`forum_name`, p.`forum_parent`, p.`forum_type`
            FROM `msz_forum_categories` as p
            INNER JOIN breadcrumbs
            ON p.`forum_id` = breadcrumbs.forum_parent
        )
        SELECT * FROM breadcrumbs
    ');
    $getBreadcrumbs->bindValue('forum_id', $forumId);
    $breadcrumbsDb = db_fetch_all($getBreadcrumbs);

    if (!$breadcrumbsDb) {
        return [$indexLink];
    }

    foreach ($breadcrumbsDb as $breadcrumb) {
        $breadcrumbs[$breadcrumb['forum_name']] = sprintf(
            $breadcrumb['forum_parent'] === MSZ_FORUM_ROOT
            && $breadcrumb['forum_type'] === MSZ_FORUM_TYPE_CATEGORY
                ? $rootFormat
                : $linkFormat,
            $breadcrumb['forum_id']
        );
    }

    return array_reverse($breadcrumbs + $indexLink);
}

function forum_get_colour(int $forumId): int
{
    $getColours = db_prepare('
        WITH RECURSIVE breadcrumbs(forum_id, forum_parent, forum_colour) as (
            SELECT c.`forum_id`, c.`forum_parent`, c.`forum_colour`
            FROM `msz_forum_categories` as c
            WHERE `forum_id` = :forum_id
            UNION ALL
            SELECT p.`forum_id`, p.`forum_parent`, p.`forum_colour`
            FROM `msz_forum_categories` as p
            INNER JOIN breadcrumbs
            ON p.`forum_id` = breadcrumbs.forum_parent
        )
        SELECT * FROM breadcrumbs
    ');
    $getColours->bindValue('forum_id', $forumId);
    $colours = db_fetch_all($getColours);

    if ($colours) {
        foreach ($colours as $colour) {
            if ($colour['forum_colour'] !== null) {
                return $colour['forum_colour'];
            }
        }
    }

    return colour_none();
}

function forum_increment_clicks(int $forumId): void
{
    $incrementLinkClicks = db_prepare(sprintf('
        UPDATE `msz_forum_categories`
        SET `forum_link_clicks` = `forum_link_clicks` + 1
        WHERE `forum_id` = :forum_id
        AND `forum_type` = %d
        AND `forum_link_clicks` IS NOT NULL
    ', MSZ_FORUM_TYPE_LINK));
    $incrementLinkClicks->bindValue('forum_id', $forumId);
    $incrementLinkClicks->execute();
}

function forum_get_child_ids(int $forumId): array
{
    if ($forumId < 1) {
        return [];
    }

    static $memoized = [];

    if (array_key_exists($forumId, $memoized)) {
        return $memoized[$forumId];
    }

    $getChildren = db_prepare('
        SELECT `forum_id`
        FROM `msz_forum_categories`
        WHERE `forum_parent` = :forum_id
    ');
    $getChildren->bindValue('forum_id', $forumId);
    $children = db_fetch_all($getChildren);

    return $memoized[$forumId] = array_column($children, 'forum_id');
}

// TODO: Permissions checks
function forum_topics_unread(int $forumId, int $userId): int
{
    if ($userId < 1 || $forumId < 1) {
        return false;
    }

    static $memoized = [];
    $memoId = "{$forumId}-{$userId}";

    if (array_key_exists($memoId, $memoized)) {
        return $memoized[$memoId];
    }

    $memoized[$memoId] = 0;
    $children = forum_get_child_ids($forumId);

    foreach ($children as $child) {
        $memoized[$memoId] += forum_topics_unread($child, $userId);
    }

    $countUnread = db_prepare('
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
    $countUnread->bindValue('forum_id', $forumId);
    $countUnread->bindValue('user_id', $userId);
    $memoized[$memoId] += (int)($countUnread->execute() ? $countUnread->fetchColumn() : 0);

    return $memoized[$memoId];
}

// TODO: Permission checks
function forum_latest_post(int $forumId, int $userId): array
{
    if ($forumId < 1) {
        return [];
    }

    static $memoized = [];
    $memoId = "{$forumId}-{$userId}";

    if (array_key_exists($memoId, $memoized)) {
        return $memoized[$memoId];
    }

    $getLastPost = db_prepare('
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
        ORDER BY p.`post_id` DESC
    ');
    $getLastPost->bindValue('forum_id', $forumId);
    $currentLast = db_fetch($getLastPost);

    $children = forum_get_child_ids($forumId);

    foreach($children as $child) {
        $lastPost = forum_latest_post($child, $userId);

        if (($currentLast['post_created_unix'] ?? 0) < ($lastPost['post_created_unix'] ?? 0)) {
            $currentLast = $lastPost;
        }
    }

    return $memoized[$memoId] = $currentLast;
}

function forum_get_children(int $parentId, int $userId, bool $showDeleted = false): array
{
    $getListing = db_prepare(sprintf(
        '
            SELECT
                :user_id AS `target_user_id`,
                f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`,
                f.`forum_link`, f.`forum_link_clicks`, f.`forum_archived`, f.`forum_colour`,
                f.`forum_count_topics`, f.`forum_count_posts`,
                (%3$s) AS `forum_permissions`
            FROM `msz_forum_categories` AS f
            WHERE f.`forum_parent` = :parent_id
            AND f.`forum_hidden` = 0
            AND (
                (f.`forum_parent` = %1$d AND f.`forum_type` != %2$d)
                OR f.`forum_parent` != %1$d
            )
            GROUP BY f.`forum_id`
            HAVING (`forum_permissions` & %4$d) > 0
            ORDER BY f.`forum_order`
        ',
        MSZ_FORUM_ROOT,
        MSZ_FORUM_TYPE_CATEGORY,
        forum_perms_get_user_sql(MSZ_FORUM_PERMS_GENERAL, 'f.`forum_id`'),
        MSZ_FORUM_PERM_SET_READ,
        $showDeleted ? '' : 'AND `topic_deleted` IS NULL',
        $showDeleted ? '' : 'AND `post_deleted` IS NULL'
    ));

    $getListing->bindValue('user_id', $userId);
    $getListing->bindValue('perm_user_id_user', $userId);
    $getListing->bindValue('perm_user_id_role', $userId);
    $getListing->bindValue('parent_id', $parentId);

    $listing = db_fetch_all($getListing);

    foreach ($listing as $key => $forum) {
        $listing[$key] = array_merge(
            $forum,
            ['forum_unread' => forum_topics_unread($forum['forum_id'], $userId)],
            forum_latest_post($forum['forum_id'], $userId)
        );
    }

    return $listing;
}

function forum_timeout(int $forumId, int $userId): int
{
    $checkTimeout = db_prepare('
        SELECT TIMESTAMPDIFF(SECOND, COALESCE(MAX(`post_created`), NOW() - INTERVAL 1 YEAR), NOW())
        FROM `msz_forum_posts`
        WHERE `forum_id` = :forum_id
        AND `user_id` = :user_id
    ');
    $checkTimeout->bindValue('forum_id', $forumId);
    $checkTimeout->bindValue('user_id', $userId);

    return (int)($checkTimeout->execute() ? $checkTimeout->fetchColumn() : 0);
}

// $forumId == null marks all forums as read
function forum_mark_read(?int $forumId, int $userId): void
{
    if (($forumId !== null && $forumId < 1) || $userId < 1) {
        return;
    }

    $entireForum = $forumId === null;
    $doMark = db_prepare(sprintf(
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
            HAVING ((%2$s) & %3$d) > 0
            ON DUPLICATE KEY UPDATE
                `track_last_read` = NOW()
        ',
        $entireForum ? '' : 'AND t.`forum_id` = :forum',
        forum_perms_get_user_sql(MSZ_FORUM_PERMS_GENERAL, 't.`forum_id`', 'u.`user_id`', 'u.`user_id`'),
        MSZ_FORUM_PERM_SET_READ
    ));
    $doMark->bindValue('user', $userId);

    if (!$entireForum) {
        $doMark->bindValue('forum', $forumId);
    }

    $doMark->execute();
}

function forum_posting_info(int $userId): array
{
    $getPostingInfo = db_prepare('
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
    $getPostingInfo->bindValue('user_id', $userId);
    return db_fetch($getPostingInfo);
}

function forum_count_increase(int $forumId, bool $topic = false): void
{
    $increaseCount = db_prepare(sprintf(
        '
            UPDATE `msz_forum_categories`
            SET `forum_count_posts` = `forum_count_posts` + 1
                %s
            WHERE `forum_id` = :forum
        ',
        $topic ? ',`forum_count_topics` = `forum_count_topics` + 1' : ''
    ));
    $increaseCount->bindValue('forum', $forumId);
    $increaseCount->execute();
}

function forum_count_synchronise(int $forumId = MSZ_FORUM_ROOT, bool $save = true): array
{
    static $getChildren = null;
    static $getCounts = null;
    static $setCounts = null;

    if (is_null($getChildren)) {
        $getChildren = db_prepare('
            SELECT `forum_id`, `forum_parent`
            FROM `msz_forum_categories`
            WHERE `forum_parent` = :parent
        ');
    }

    if (is_null($getCounts)) {
        $getCounts = db_prepare('
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

    if ($save && is_null($setCounts)) {
        $setCounts = db_prepare('
            UPDATE `msz_forum_categories`
            SET `forum_count_topics` = :topics,
                `forum_count_posts` = :posts
            WHERE `forum_id` = :forum_id
        ');
    }

    $getChildren->bindValue('parent', $forumId);
    $children = db_fetch_all($getChildren);

    $topics = 0;
    $posts = 0;

    foreach ($children as $child) {
        $childCount = forum_count_synchronise($child['forum_id'], $save);
        $topics += $childCount['topics'];
        $posts += $childCount['posts'];
    }

    $getCounts->bindValue('forum', $forumId);
    $counts = db_fetch($getCounts);
    $topics += $counts['count_topics'];
    $posts += $counts['count_posts'];

    if ($forumId > 0 && $save) {
        $setCounts->bindValue('forum_id', $forumId);
        $setCounts->bindValue('topics', $topics);
        $setCounts->bindValue('posts', $posts);
        $setCounts->execute();
    }

    return compact('topics', 'posts');
}
