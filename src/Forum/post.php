<?php
define('MSZ_FORUM_POSTS_PER_PAGE', 10);

function forum_post_create(
    int $topicId,
    int $forumId,
    int $userId,
    string $ipAddress,
    string $text,
    int $parser = \Misuzu\Parsers\Parser::PLAIN,
    bool $displaySignature = true
): int {
    $createPost = \Misuzu\DB::prepare('
        INSERT INTO `msz_forum_posts`
            (`topic_id`, `forum_id`, `user_id`, `post_ip`, `post_text`, `post_parse`, `post_display_signature`)
        VALUES
            (:topic_id, :forum_id, :user_id, INET6_ATON(:post_ip), :post_text, :post_parse, :post_display_signature)
    ');
    $createPost->bind('topic_id', $topicId);
    $createPost->bind('forum_id', $forumId);
    $createPost->bind('user_id', $userId);
    $createPost->bind('post_ip', $ipAddress);
    $createPost->bind('post_text', $text);
    $createPost->bind('post_parse', $parser);
    $createPost->bind('post_display_signature', $displaySignature ? 1 : 0);

    return $createPost->execute() ? \Misuzu\DB::lastId() : 0;
}

function forum_post_update(
    int $postId,
    string $ipAddress,
    string $text,
    int $parser = \Misuzu\Parsers\Parser::PLAIN,
    bool $displaySignature = true,
    bool $bumpUpdate = true
): bool {
    if($postId < 1) {
        return false;
    }

    $updatePost = \Misuzu\DB::prepare('
        UPDATE `msz_forum_posts`
        SET `post_ip` = INET6_ATON(:post_ip),
            `post_text` = :post_text,
            `post_parse` = :post_parse,
            `post_display_signature` = :post_display_signature,
            `post_edited` = IF(:bump, NOW(), `post_edited`)
        WHERE `post_id` = :post_id
    ');
    $updatePost->bind('post_id', $postId);
    $updatePost->bind('post_ip', $ipAddress);
    $updatePost->bind('post_text', $text);
    $updatePost->bind('post_parse', $parser);
    $updatePost->bind('post_display_signature', $displaySignature ? 1 : 0);
    $updatePost->bind('bump', $bumpUpdate ? 1 : 0);

    return $updatePost->execute();
}

function forum_post_find(int $postId, int $userId): array {
    $getPostInfo = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`topic_id`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                    AND `post_id` < p.`post_id`
                    AND `post_deleted` IS NULL
                    ORDER BY `post_id`
                ) as `preceeding_post_count`,
                (
                    SELECT COUNT(`post_id`)
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                    AND `post_id` < p.`post_id`
                    AND `post_deleted` IS NOT NULL
                    ORDER BY `post_id`
                ) as `preceeding_post_deleted_count`
            FROM `msz_forum_posts` AS p
            WHERE p.`post_id` = :post_id
        '));
    $getPostInfo->bind('post_id', $postId);
    return $getPostInfo->fetch();
}

function forum_post_get(int $postId, bool $allowDeleted = false): array {
    $getPost = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`post_text`, p.`post_created`, p.`post_parse`, p.`post_display_signature`,
                p.`topic_id`, p.`post_deleted`, p.`post_edited`, p.`topic_id`, p.`forum_id`,
                INET6_NTOA(p.`post_ip`) AS `post_ip`,
                u.`user_id` AS `poster_id`, u.`username` AS `poster_name`,
                u.`user_created` AS `poster_joined`, u.`user_country` AS `poster_country`,
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
                ) AS `is_opening_post`,
                (
                    SELECT `user_id` = u.`user_id`
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                    ORDER BY `post_id`
                    LIMIT 1
                ) AS `is_original_poster`
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
    $getPost->bind('post_id', $postId);
    return $getPost->fetch();
}

function forum_post_search(string $query): array {
    $searchPosts = \Misuzu\DB::prepare('
        SELECT
            p.`post_id`, p.`post_text`, p.`post_created`, p.`post_parse`, p.`post_display_signature`,
            p.`topic_id`, p.`post_deleted`, p.`post_edited`, p.`topic_id`, p.`forum_id`,
            INET6_NTOA(p.`post_ip`) AS `post_ip`,
            u.`user_id` AS `poster_id`, u.`username` AS `poster_name`,
            u.`user_created` AS `poster_joined`, u.`user_country` AS `poster_country`,
            u.`user_signature_content` AS `poster_signature_content`, u.`user_signature_parser` AS `poster_signature_parser`,
            COALESCE(u.`user_colour`, r.`role_colour`) AS `poster_colour`,
            COALESCE(u.`user_title`, r.`role_title`) AS `poster_title`,
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
            ) AS `is_opening_post`,
            (
                SELECT `user_id` = u.`user_id`
                FROM `msz_forum_posts`
                WHERE `topic_id` = p.`topic_id`
                ORDER BY `post_id`
                LIMIT 1
            ) AS `is_original_poster`
        FROM `msz_forum_posts` AS p
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
        WHERE MATCH(p.`post_text`)
        AGAINST (:query IN NATURAL LANGUAGE MODE)
        AND `post_deleted` IS NULL
        ORDER BY `post_id`
    ');
    $searchPosts->bind('query', $query);
    return $searchPosts->fetchAll();
}

function forum_post_count_user(int $userId, bool $showDeleted = false): int {
    $getPosts = \Misuzu\DB::prepare(sprintf(
        '
            SELECT COUNT(p.`post_id`)
            FROM `msz_forum_posts` AS p
            WHERE `user_id` = :user_id
            %1$s
        ',
        $showDeleted ? '' : 'AND `post_deleted` IS NULL'
    ));
    $getPosts->bind('user_id', $userId);

    return (int)$getPosts->fetchColumn();
}

function forum_post_listing(
    int $topicId,
    int $offset = 0,
    int $take = 0,
    bool $showDeleted = false,
    bool $selectAuthor = false
): array {
    $hasPagination = $offset >= 0 && $take > 0;
    $getPosts = \Misuzu\DB::prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`post_text`, p.`post_created`, p.`post_parse`,
                p.`topic_id`, p.`post_deleted`, p.`post_edited`, p.`post_display_signature`,
                INET6_NTOA(p.`post_ip`) AS `post_ip`,
                u.`user_id` AS `poster_id`, u.`username` AS `poster_name`,
                u.`user_created` AS `poster_joined`, u.`user_country` AS `poster_country`,
                u.`user_signature_content` AS `poster_signature_content`, u.`user_signature_parser` AS `poster_signature_parser`,
                COALESCE(u.`user_colour`, r.`role_colour`) AS `poster_colour`,
                COALESCE(u.`user_title`, r.`role_title`) AS `poster_title`,
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
                ) AS `is_opening_post`,
                (
                    SELECT `user_id` = u.`user_id`
                    FROM `msz_forum_posts`
                    WHERE `topic_id` = p.`topic_id`
                    ORDER BY `post_id`
                    LIMIT 1
                ) AS `is_original_poster`
            FROM `msz_forum_posts` AS p
            LEFT JOIN `msz_users` AS u
            ON u.`user_id` = p.`user_id`
            LEFT JOIN `msz_roles` AS r
            ON r.`role_id` = u.`display_role`
            WHERE %3$s = :topic_id
            %1$s
            ORDER BY `post_id`
            %2$s
        ',
        $showDeleted ? '' : 'AND `post_deleted` IS NULL',
        $hasPagination ? 'LIMIT :offset, :take' : '',
        $selectAuthor ? 'p.`user_id`' : 'p.`topic_id`'
    ));
    $getPosts->bind('topic_id', $topicId);

    if($hasPagination) {
        $getPosts->bind('offset', $offset);
        $getPosts->bind('take', $take);
    }

    return $getPosts->fetchAll();
}

define('MSZ_E_FORUM_POST_DELETE_OK', 0);        // deleting is fine
define('MSZ_E_FORUM_POST_DELETE_USER', 1);      // invalid user
define('MSZ_E_FORUM_POST_DELETE_POST', 2);      // post doesn't exist
define('MSZ_E_FORUM_POST_DELETE_DELETED', 3);   // post is already marked as deleted
define('MSZ_E_FORUM_POST_DELETE_OWNER', 4);     // you may only delete your own posts
define('MSZ_E_FORUM_POST_DELETE_OLD', 5);       // posts has existed for too long to be deleted
define('MSZ_E_FORUM_POST_DELETE_PERM', 6);      // you aren't allowed to delete posts
define('MSZ_E_FORUM_POST_DELETE_OP', 7);        // this is the opening post of a topic

// only allow posts made within a week of posting to be deleted by normal users
define('MSZ_FORUM_POST_DELETE_LIMIT', 60 * 60 * 24 * 7);

// set $userId to null for system request, make sure this is NEVER EVER null on user request
// $postId can also be a the return value of forum_post_get if you already grabbed it once before
function forum_post_can_delete($postId, ?int $userId = null): int {
    if($userId !== null && $userId < 1) {
        return MSZ_E_FORUM_POST_DELETE_USER;
    }

    if(is_array($postId)) {
        $post = $postId;
    } else {
        $post = forum_post_get((int)$postId, true);
    }

    if(empty($post)) {
        return MSZ_E_FORUM_POST_DELETE_POST;
    }

    $isSystemReq    = $userId === null;
    $perms          = $isSystemReq ? 0      : forum_perms_get_user($post['forum_id'], $userId)[MSZ_FORUM_PERMS_GENERAL];
    $canDeleteAny   = $isSystemReq ? true   : perms_check($perms, MSZ_FORUM_PERM_DELETE_ANY_POST);
    $canViewPost    = $isSystemReq ? true   : perms_check($perms, MSZ_FORUM_PERM_VIEW_FORUM);
    $postIsDeleted  = !empty($post['post_deleted']);

    if(!$canViewPost) {
        return MSZ_E_FORUM_POST_DELETE_POST;
    }

    if($post['is_opening_post']) {
        return MSZ_E_FORUM_POST_DELETE_OP;
    }

    if($postIsDeleted) {
        return $canDeleteAny ? MSZ_E_FORUM_POST_DELETE_DELETED : MSZ_E_FORUM_POST_DELETE_POST;
    }

    if($isSystemReq) {
        return MSZ_E_FORUM_POST_DELETE_OK;
    }

    if(!$canDeleteAny) {
        if(!perms_check($perms, MSZ_FORUM_PERM_DELETE_POST)) {
            return MSZ_E_FORUM_POST_DELETE_PERM;
        }

        if($post['poster_id'] !== $userId) {
            return MSZ_E_FORUM_POST_DELETE_OWNER;
        }

        if(strtotime($post['post_created']) <= time() - MSZ_FORUM_POST_DELETE_LIMIT) {
            return MSZ_E_FORUM_POST_DELETE_OLD;
        }
    }

    return MSZ_E_FORUM_POST_DELETE_OK;
}

function forum_post_delete(int $postId): bool {
    if($postId < 1) {
        return false;
    }

    $markDeleted = \Misuzu\DB::prepare('
        UPDATE `msz_forum_posts`
        SET `post_deleted` = NOW()
        WHERE `post_id` = :post
        AND `post_deleted` IS NULL
    ');
    $markDeleted->bind('post', $postId);
    return $markDeleted->execute();
}

function forum_post_restore(int $postId): bool {
    if($postId < 1) {
        return false;
    }

    $markDeleted = \Misuzu\DB::prepare('
        UPDATE `msz_forum_posts`
        SET `post_deleted` = NULL
        WHERE `post_id` = :post
        AND `post_deleted` IS NOT NULL
    ');
    $markDeleted->bind('post', $postId);
    return $markDeleted->execute();
}

function forum_post_nuke(int $postId): bool {
    if($postId < 1) {
        return false;
    }

    $markDeleted = \Misuzu\DB::prepare('
        DELETE FROM `msz_forum_posts`
        WHERE `post_id` = :post
    ');
    $markDeleted->bind('post', $postId);
    return $markDeleted->execute();
}
