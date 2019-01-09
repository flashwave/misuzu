<?php
define('MSZ_FORUM_POSTS_PER_PAGE', 10);

function forum_post_create(
    int $topicId,
    int $forumId,
    int $userId,
    string $ipAddress,
    string $text,
    int $parser = MSZ_PARSER_PLAIN
): int {
    $createPost = db_prepare('
        INSERT INTO `msz_forum_posts`
            (`topic_id`, `forum_id`, `user_id`, `post_ip`, `post_text`, `post_parse`)
        VALUES
            (:topic_id, :forum_id, :user_id, INET6_ATON(:post_ip), :post_text, :post_parse)
    ');
    $createPost->bindValue('topic_id', $topicId);
    $createPost->bindValue('forum_id', $forumId);
    $createPost->bindValue('user_id', $userId);
    $createPost->bindValue('post_ip', $ipAddress);
    $createPost->bindValue('post_text', $text);
    $createPost->bindValue('post_parse', $parser);

    return $createPost->execute() ? db_last_insert_id() : 0;
}

function forum_post_update(
    int $postId,
    string $ipAddress,
    string $text,
    int $parser = MSZ_PARSER_PLAIN,
    bool $bumpUpdate = true
): bool {
    if ($postId < 1) {
        return false;
    }

    $updatePost = db_prepare('
        UPDATE `msz_forum_posts`
        SET `post_ip` = INET6_ATON(:post_ip),
            `post_text` = :post_text,
            `post_parse` = :post_parse,
            `post_edited` = IF(:bump, NOW(), `post_edited`)
        WHERE `post_id` = :post_id
    ');
    $updatePost->bindValue('post_id', $postId);
    $updatePost->bindValue('post_ip', $ipAddress);
    $updatePost->bindValue('post_text', $text);
    $updatePost->bindValue('post_parse', $parser);
    $updatePost->bindValue('bump', $bumpUpdate ? 1 : 0);

    return $updatePost->execute();
}

function forum_post_find(int $postId, int $userId): array
{
    $getPostInfo = db_prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`topic_id`,
                ((%s) & %d) as `can_view_deleted`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                    AND `post_id` < p.`post_id`
                    AND (`can_view_deleted` OR `post_deleted` IS NULL)
                    ORDER BY `post_id`
                ) as `preceeding_post_count`
            FROM `msz_forum_posts` AS p
            WHERE p.`post_id` = :post_id
        ',
        forum_perms_get_user_sql(MSZ_FORUM_PERMS_GENERAL, 'p.`forum_id`'),
        MSZ_FORUM_PERM_DELETE_TOPIC | MSZ_FORUM_PERM_DELETE_ANY_POST
    ));
    $getPostInfo->bindValue('post_id', $postId);
    $getPostInfo->bindValue('perm_user_id_user', $userId);
    $getPostInfo->bindValue('perm_user_id_role', $userId);
    return db_fetch($getPostInfo);
}

function forum_post_get(int $postId, bool $allowDeleted = false): array
{
    $getPost = db_prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`post_text`, p.`post_created`, p.`post_parse`,
                p.`topic_id`, p.`post_deleted`, p.`post_edited`,
                INET6_NTOA(p.`post_ip`) AS `post_ip`,
                u.`user_id` AS `poster_id`,
                u.`username` AS `poster_name`,
                u.`user_created` AS `poster_joined`,
                u.`user_country` AS `poster_country`,
                COALESCE(u.`user_colour`, r.`role_colour`) AS `poster_colour`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `user_id` = p.`user_id`
                    AND `post_deleted` IS NULL
                ) AS `poster_post_count`,
                (
                    SELECT MIN(`post_id`) = p.`post_id`
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                ) AS `is_opening_post`
            FROM `msz_forum_posts` AS p
            LEFT JOIN `msz_users` AS u
            ON u.`user_id` = p.`user_id`
            LEFT JOIN `msz_roles` AS r
            ON r.`role_id` = u.`display_role`
            WHERE `post_id` = :post_id
            %1$s
            ORDER BY `post_id`
        ',
        $allowDeleted ? '' : 'AND `post_deleted` IS NULL'
    ));
    $getPost->bindValue('post_id', $postId);
    return db_fetch($getPost);
}

function forum_post_listing(int $topicId, int $offset = 0, int $take = 0, bool $showDeleted = false): array
{
    $hasPagination = $offset >= 0 && $take > 0;
    $getPosts = db_prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`post_text`, p.`post_created`, p.`post_parse`,
                p.`topic_id`, p.`post_deleted`, p.`post_edited`,
                INET6_NTOA(p.`post_ip`) AS `post_ip`,
                u.`user_id` AS `poster_id`,
                u.`username` AS `poster_name`,
                u.`user_created` AS `poster_joined`,
                u.`user_country` AS `poster_country`,
                COALESCE(u.`user_colour`, r.`role_colour`) AS `poster_colour`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `user_id` = p.`user_id`
                    AND `post_deleted` IS NULL
                ) AS `poster_post_count`,
                (
                    SELECT MIN(`post_id`) = p.`post_id`
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                ) AS `is_opening_post`
            FROM `msz_forum_posts` AS p
            LEFT JOIN `msz_users` AS u
            ON u.`user_id` = p.`user_id`
            LEFT JOIN `msz_roles` AS r
            ON r.`role_id` = u.`display_role`
            WHERE `topic_id` = :topic_id
            %1$s
            ORDER BY `post_id`
            %2$s
        ',
        $showDeleted ? '' : 'AND `post_deleted` IS NULL',
        $hasPagination ? 'LIMIT :offset, :take' : ''
    ));
    $getPosts->bindValue('topic_id', $topicId);

    if ($hasPagination) {
        $getPosts->bindValue('offset', $offset);
        $getPosts->bindValue('take', $take);
    }

    return db_fetch_all($getPosts);
}
