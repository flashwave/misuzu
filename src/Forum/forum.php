<?php
use Misuzu\Database;

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
    'forum_id' => 0,
    'forum_name' => 'Forums',
    'forum_children' => 0,
    'forum_type' => MSZ_FORUM_TYPE_CATEGORY,
]);

function forum_may_have_children(int $forumType): bool
{
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_CHILDREN);
}

function forum_may_have_topics(int $forumType): bool
{
    return in_array($forumType, MSZ_FORUM_MAY_HAVE_TOPICS);
}

function forum_fetch(int $forumId): array
{
    $getForum = Database::connection()->prepare('
        SELECT
            `forum_id`, `forum_name`, `forum_type`, `forum_link`, `forum_link_clicks`, `forum_parent`,
            (
                SELECT COUNT(`topic_id`)
                FROM `msz_forum_topics`
                WHERE `forum_id` = f.`forum_id`
            ) as `forum_topic_count`
        FROM `msz_forum_categories` as f
        WHERE `forum_id` = :forum_id
    ');
    $getForum->bindValue('forum_id', $forumId);
    $getForum->execute();
    $forums = $getForum->fetch();

    return $forums ? $forums : [];
}

function forum_get_root_categories(): array
{
    $dbc = Database::connection();

    $categories = $dbc->query('
        SELECT
            f.`forum_id`, f.`forum_name`, f.`forum_type`,
            (
                SELECT COUNT(`forum_id`)
                FROM `msz_forum_categories` as sf
                WHERE sf.`forum_parent` = f.`forum_id`
            ) as `forum_children`
        FROM `msz_forum_categories` as f
        WHERE f.`forum_parent` = 0
        AND f.`forum_type` = 1
        AND f.`forum_hidden` = false
        ORDER BY f.`forum_order`
    ')->fetchAll();

    $categories = array_merge([MSZ_FORUM_ROOT_DATA], $categories);

    $categories[0]['forum_children'] = (int)$dbc->query('
        SELECT COUNT(`forum_id`)
        FROM `msz_forum_categories`
        WHERE `forum_parent` = ' . MSZ_FORUM_ROOT . '
    ')->fetchColumn();

    return $categories;
}

function forum_get_breadcrumbs(
    int $forumId,
    string $linkFormat = '/forum/forum.php?f=%d',
    array $indexLink = ['Forums' => '/forum/']
): array {
    $breadcrumbs = [];
    $getBreadcrumb = Database::connection()->prepare('
        SELECT `forum_id`, `forum_name`, `forum_parent`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');

    while ($forumId > MSZ_FORUM_ROOT) {
        $getBreadcrumb->bindValue('forum_id', $forumId);
        $breadcrumb = $getBreadcrumb->execute() ? $getBreadcrumb->fetch() : [];

        if (!$breadcrumb) {
            break;
        }

        $breadcrumbs[$breadcrumb['forum_name']] = sprintf($linkFormat, $breadcrumb['forum_id']);
        $forumId = $breadcrumb['forum_parent'];
    }

    return array_reverse($breadcrumbs + $indexLink);
}

function forum_increment_clicks(int $forumId): void
{
    $incrementLinkClicks = Database::connection()->prepare('
        UPDATE `msz_forum_categories`
        SET `forum_link_clicks` = `forum_link_clicks` + 1
        WHERE `forum_id` = :forum_id
        AND `forum_type` = ' . MSZ_FORUM_TYPE_LINK . '
        AND `forum_link_clicks` IS NOT NULL
    ');
    $incrementLinkClicks->bindValue('forum_id', $forumId);
    $incrementLinkClicks->execute();
}

define('MSZ_FORUM_GET_CHILDREN_QUERY_SMALL', '
    SELECT
        :user_id as `target_user_id`,
        f.`forum_id`, f.`forum_name`,
        (
            SELECT
                `target_user_id` > 0
            AND
                t.`topic_id` IS NOT NULL
            AND
                t.`topic_bumped` >= NOW() - INTERVAL 1 MONTH
            AND (
                SELECT COUNT(ti.`topic_id`) < (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics`
                    WHERE `forum_id` = f.`forum_id`
                    AND `topic_bumped` >= NOW() - INTERVAL 1 MONTH
                    AND `topic_deleted` IS NULL
                )
                FROM `msz_forum_topics_track` as tt
                RIGHT JOIN `msz_forum_topics` as ti
                ON ti.`topic_id` = tt.`topic_id`
                WHERE ti.`forum_id` = f.`forum_id`
                AND tt.`user_id` = `target_user_id`
                AND  `track_last_read` >= `topic_bumped`
            )
        ) as `forum_unread`
    FROM `msz_forum_categories` as f
    LEFT JOIN `msz_forum_topics` as t
    ON t.`topic_id` = (
        SELECT `topic_id`
        FROM `msz_forum_topics`
        WHERE `forum_id` = f.`forum_id`
        AND `topic_deleted` IS NULL
        ORDER BY `topic_bumped` DESC
        LIMIT 1
    )
    WHERE `forum_parent` = :parent_id
    AND `forum_hidden` = false
    ORDER BY f.`forum_order`
');
define('MSZ_FORUM_GET_CHILDREN_QUERY_STANDARD', '
    SELECT
        :user_id as `target_user_id`,
        f.`forum_id`, f.`forum_name`, f.`forum_description`, f.`forum_type`,
        f.`forum_link`, f.`forum_link_clicks`, f.`forum_archived`,
        t.`topic_id` as `recent_topic_id`, p.`post_id` as `recent_post_id`,
        t.`topic_title` as `recent_topic_title`, t.`topic_bumped` as `recent_topic_bumped`,
        p.`post_created` as `recent_post_created`,
        u.`user_id` as `recent_post_user_id`,
        u.`username` as `recent_post_username`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `recent_post_user_colour`,
        (
            SELECT COUNT(`topic_id`)
            FROM `msz_forum_topics`
            WHERE `forum_id` = f.`forum_id`
        ) as `forum_topic_count`,
        (
            SELECT COUNT(`post_id`)
            FROM `msz_forum_posts`
            WHERE `forum_id` = f.`forum_id`
        ) as `forum_post_count`,
        (
            SELECT
                `target_user_id` > 0
            AND
                `recent_topic_id` IS NOT NULL
            AND
                `recent_topic_bumped` >= NOW() - INTERVAL 1 MONTH
            AND (
                SELECT COUNT(ti.`topic_id`) < (
                    SELECT COUNT(`topic_id`)
                    FROM `msz_forum_topics`
                    WHERE `forum_id` = f.`forum_id`
                    AND `topic_bumped` >= NOW() - INTERVAL 1 MONTH
                    AND `topic_deleted` IS NULL
                )
                FROM `msz_forum_topics_track` as tt
                RIGHT JOIN `msz_forum_topics` as ti
                ON ti.`topic_id` = tt.`topic_id`
                WHERE ti.`forum_id` = f.`forum_id`
                AND tt.`user_id` = `target_user_id`
                AND  `track_last_read` >= `topic_bumped`
            )
        ) as `forum_unread`
    FROM `msz_forum_categories` as f
    LEFT JOIN `msz_forum_topics` as t
    ON t.`topic_id` = (
        SELECT `topic_id`
        FROM `msz_forum_topics`
        WHERE `forum_id` = f.`forum_id`
        AND `topic_deleted` IS NULL
        ORDER BY `topic_bumped` DESC
        LIMIT 1
    )
    LEFT JOIN `msz_forum_posts` as p
    ON p.`post_id` = (
        SELECT `post_id`
        FROM `msz_forum_posts`
        WHERE `topic_id` = t.`topic_id`
        ORDER BY `post_id` DESC
        LIMIT 1
    )
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = p.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE f.`forum_parent` = :parent_id
    AND f.`forum_hidden` = false
    AND (
        (f.`forum_parent` = ' . MSZ_FORUM_ROOT . ' AND f.`forum_type` != ' . MSZ_FORUM_TYPE_CATEGORY . ')
        OR f.`forum_parent` != ' . MSZ_FORUM_ROOT . '
    )
    ORDER BY f.`forum_order`
');

function forum_get_children(int $parentId, int $userId, bool $small = false): array
{
    $getListing = Database::connection()->prepare(
        $small
        ? MSZ_FORUM_GET_CHILDREN_QUERY_SMALL
        : MSZ_FORUM_GET_CHILDREN_QUERY_STANDARD
    );
    $getListing->bindValue('user_id', $userId);
    $getListing->bindValue('parent_id', $parentId);

    return $getListing->execute() ? $getListing->fetchAll() : [];
}
