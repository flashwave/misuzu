<?php
require_once 'Users/validation.php';

define('MSZ_PERM_COMMENTS_CREATE', 1);
define('MSZ_PERM_COMMENTS_EDIT_OWN', 1 << 1);
define('MSZ_PERM_COMMENTS_EDIT_ANY', 1 << 2);
define('MSZ_PERM_COMMENTS_DELETE_OWN', 1 << 3);
define('MSZ_PERM_COMMENTS_DELETE_ANY', 1 << 4);
define('MSZ_PERM_COMMENTS_PIN', 1 << 5);
define('MSZ_PERM_COMMENTS_LOCK', 1 << 6);
define('MSZ_PERM_COMMENTS_VOTE', 1 << 7);

define('MSZ_COMMENTS_VOTE_INDIFFERENT', null);
define('MSZ_COMMENTS_VOTE_LIKE', 'Like');
define('MSZ_COMMENTS_VOTE_DISLIKE', 'Dislike');
define('MSZ_COMMENTS_VOTE_TYPES', [
    0 => MSZ_COMMENTS_VOTE_INDIFFERENT,
    1 => MSZ_COMMENTS_VOTE_LIKE,
    -1 => MSZ_COMMENTS_VOTE_DISLIKE,
]);

// gets parsed on post
define('MSZ_COMMENTS_MARKUP_USERNAME', '#\B(?:@{1}(' . MSZ_USERNAME_REGEX . '))#u');

// gets parsed on fetch
define('MSZ_COMMENTS_MARKUP_USER_ID', '#\B(?:@{2}([0-9]+))#u');

function comments_parse_for_store(string $text): string
{
    return preg_replace_callback(MSZ_COMMENTS_MARKUP_USERNAME, function ($matches) {
        return ($userId = user_id_from_username($matches[1])) < 1
            ? $matches[0]
            : "@@{$userId}";
    }, $text);
}

function comments_parse_for_display(string $text): string
{
    return preg_replace_callback(MSZ_COMMENTS_MARKUP_USER_ID, function ($matches) {
        $getInfo = db_prepare('
            SELECT
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE `user_id` = :user_id
        ');
        $getInfo->bindValue('user_id', $matches[1]);
        $info = $getInfo->execute() ? $getInfo->fetch(PDO::FETCH_ASSOC) : [];

        if (!$info) {
            return $matches[0];
        }

        return sprintf(
            '<a href="/profile.php?u=%d" class="comment__mention", style="%s">@%s</a>',
            $info['user_id'],
            html_colour($info['user_colour']),
            $info['username']
        );
    }, $text);
}

// usually this is not how you're suppose to handle permission checking,
// but in the context of comments this is fine since the same shit is used
// for every comment section.
function comments_get_perms(int $userId): array
{
    $perms = perms_get_user(MSZ_PERMS_COMMENTS, $userId);
    return [
        'can_comment' => perms_check($perms, MSZ_PERM_COMMENTS_CREATE),
        'can_edit' => perms_check($perms, MSZ_PERM_COMMENTS_EDIT_OWN | MSZ_PERM_COMMENTS_EDIT_ANY),
        'can_edit_any' => perms_check($perms, MSZ_PERM_COMMENTS_EDIT_ANY),
        'can_delete' => perms_check($perms, MSZ_PERM_COMMENTS_DELETE_OWN | MSZ_PERM_COMMENTS_DELETE_ANY),
        'can_delete_any' => perms_check($perms, MSZ_PERM_COMMENTS_DELETE_ANY),
        'can_pin' => perms_check($perms, MSZ_PERM_COMMENTS_PIN),
        'can_lock' => perms_check($perms, MSZ_PERM_COMMENTS_LOCK),
        'can_vote' => perms_check($perms, MSZ_PERM_COMMENTS_VOTE),
    ];
}

function comments_vote_add(int $comment, int $user, ?string $vote): bool
{
    if (!in_array($vote, MSZ_COMMENTS_VOTE_TYPES, true)) {
        return false;
    }

    $setVote = db_prepare('
        REPLACE INTO `msz_comments_votes`
            (`comment_id`, `user_id`, `comment_vote`)
        VALUES
            (:comment, :user, :vote)
    ');
    $setVote->bindValue('comment', $comment);
    $setVote->bindValue('user', $user);
    $setVote->bindValue('vote', $vote);
    return $setVote->execute();
}

function comments_votes_get(int $commentId): array
{
    $getVotes = db_prepare('
        SELECT :id as `id`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_comments_votes`
            WHERE `comment_id` = `id`
            AND `comment_vote` = \'Like\'
        ) as `likes`,
        (
            SELECT COUNT(`user_id`)
            FROM `msz_comments_votes`
            WHERE `comment_id` = `id`
            AND `comment_vote` = \'Dislike\'
        ) as `dislikes`
    ');
    $getVotes->bindValue('id', $commentId);
    $votes = $getVotes->execute() ? $getVotes->fetch(PDO::FETCH_ASSOC) : false;
    return $votes ? $votes : [];
}

function comments_category_create(string $name): array
{
    $create = db_prepare('
        INSERT INTO `msz_comments_categories`
            (`category_name`)
        VALUES
            (LOWER(:name))
    ');
    $create->bindValue('name', $name);
    return $create->execute()
        ? comments_category_info((int)db_last_insert_id(), false)
        : [];
}

function comments_category_lock(int $category, bool $lock): void
{
    $setLock = db_prepare('
        UPDATE `msz_comments_categories`
        SET `category_locked` = IF(:lock, NOW(), NULL)
        WHERE `category_id` = :category
    ');
    $setLock->bindValue('category', $category);
    $setLock->bindValue('lock', $lock);
    $setLock->execute();
}

define('MSZ_COMMENTS_CATEGORY_INFO_QUERY', '
    SELECT
        `category_id`, `category_locked`
    FROM `msz_comments_categories`
    WHERE `%s` = %s
');
define('MSZ_COMMENTS_CATEGORY_INFO_ID', sprintf(
    MSZ_COMMENTS_CATEGORY_INFO_QUERY,
    'category_id',
    ':category'
));
define('MSZ_COMMENTS_CATEGORY_INFO_NAME', sprintf(
    MSZ_COMMENTS_CATEGORY_INFO_QUERY,
    'category_name',
    'LOWER(:category)'
));

function comments_category_info($category, bool $createIfNone = false): array
{
    if (is_int($category)) {
        $getCategory = db_prepare(MSZ_COMMENTS_CATEGORY_INFO_ID);
        $createIfNone = false;
    } elseif (is_string($category)) {
        $getCategory = db_prepare(MSZ_COMMENTS_CATEGORY_INFO_NAME);
    } else {
        return [];
    }

    $getCategory->bindValue('category', $category);
    $categoryInfo = $getCategory->execute() ? $getCategory->fetch(PDO::FETCH_ASSOC) : false;
    return $categoryInfo
        ? $categoryInfo
        : (
            $createIfNone
                ? comments_category_create($category)
                : []
        );
}

define('MSZ_COMMENTS_CATEGORY_QUERY', '
    SELECT
        p.`comment_id`, p.`comment_text`, p.`comment_reply_to`,
        p.`comment_created`, p.`comment_pinned`, p.`comment_deleted`,
        u.`user_id`, u.`username`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
        (
            SELECT COUNT(`comment_id`)
            FROM `msz_comments_votes`
            WHERE `comment_id` = p.`comment_id`
            AND `comment_vote` = \'Like\'
        ) as `comment_likes`,
        (
            SELECT COUNT(`comment_id`)
            FROM `msz_comments_votes`
            WHERE `comment_id` = p.`comment_id`
            AND `comment_vote` = \'Dislike\'
        ) as `comment_dislikes`,
        (
            SELECT `comment_vote`
            FROM `msz_comments_votes`
            WHERE `comment_id` = p.`comment_id`
            AND `user_id` = :user
        ) as `comment_user_vote`
    FROM `msz_comments_posts` as p
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = p.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    WHERE p.`category_id` = :category
    AND p.`comment_deleted` IS NULL
    %s
    ORDER BY p.`comment_pinned` DESC, p.`comment_id` %s
');
define('MSZ_COMMENTS_CATEGORY_QUERY_ROOT', sprintf(
    MSZ_COMMENTS_CATEGORY_QUERY,
    'AND p.`comment_reply_to` IS NULL',
    'DESC'
));
define('MSZ_COMMENTS_CATEGORY_QUERY_REPLIES', sprintf(
    MSZ_COMMENTS_CATEGORY_QUERY,
    'AND p.`comment_reply_to` = :parent',
    'ASC'
));

// heavily recursive
function comments_category_get(int $category, int $user, ?int $parent = null): array
{
    if ($parent !== null) {
        $getComments = db_prepare(MSZ_COMMENTS_CATEGORY_QUERY_REPLIES);
        $getComments->bindValue('parent', $parent);
    } else {
        $getComments = db_prepare(MSZ_COMMENTS_CATEGORY_QUERY_ROOT);
    }

    $getComments->bindValue('user', $user);
    $getComments->bindValue('category', $category);
    $comments = $getComments->execute() ? $getComments->fetchAll(PDO::FETCH_ASSOC) : [];

    $commentsCount = count($comments);
    for ($i = 0; $i < $commentsCount; $i++) {
        $comments[$i]['comment_html'] = nl2br(comments_parse_for_display(htmlentities($comments[$i]['comment_text'])));
        $comments[$i]['comment_replies'] = comments_category_get($category, $user, $comments[$i]['comment_id']);
    }

    return $comments;
}

function comments_post_create(
    int $user,
    int $category,
    string $text,
    bool $pinned = false,
    ?int $reply = null,
    bool $parse = true
): int {
    if ($parse) {
        $text = comments_parse_for_store($text);
    }

    $create = db_prepare('
        INSERT INTO `msz_comments_posts`
            (`user_id`, `category_id`, `comment_text`, `comment_pinned`, `comment_reply_to`)
        VALUES
            (:user, :category, :text, IF(:pin, NOW(), NULL), :reply)
    ');
    $create->bindValue('user', $user);
    $create->bindValue('category', $category);
    $create->bindValue('text', $text);
    $create->bindValue('pin', $pinned ? 1 : 0);
    $create->bindValue('reply', $reply < 1 ? null : $reply);
    return $create->execute() ? db_last_insert_id() : 0;
}

function comments_post_delete(int $commentId, bool $delete = true): bool
{
    $deleteComment = db_prepare('
        UPDATE `msz_comments_posts`
        SET `comment_deleted` = IF(:del, NOW(), NULL)
        WHERE `comment_id` = :id
    ');
    $deleteComment->bindValue('id', $commentId);
    $deleteComment->bindValue('del', $delete ? 1 : 0);
    return $deleteComment->execute();
}

function comments_post_get(int $commentId, bool $parse = true): array
{
    $fetch = db_prepare('
        SELECT
            p.`comment_id`, p.`category_id`, p.`comment_text`,
            p.`comment_created`, p.`comment_edited`, p.`comment_deleted`,
            p.`comment_reply_to`, p.`comment_pinned`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_comments_posts` as p
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE `comment_id` = :id
    ');
    $fetch->bindValue('id', $commentId);
    $comment = $fetch->execute() ? $fetch->fetch(PDO::FETCH_ASSOC) : false;
    $comment = $comment ? $comment : []; // prevent type errors

    if ($comment && $parse) {
        $comment['comment_html'] = nl2br(comments_parse_for_display(htmlentities($comment['comment_text'])));
    }

    return $comment;
}

function comments_post_exists(int $commentId): bool
{
    $fetch = db_prepare('
        SELECT COUNT(`comment_id`) > 0
        FROM `msz_comments_posts`
        WHERE `comment_id` = :id
    ');
    $fetch->bindValue('id', $commentId);
    return $fetch->execute() ? (bool)$fetch->fetchColumn() : false;
}

function comments_post_replies(int $commentId): array
{
    $getComments = db_prepare('
        SELECT
            p.`comment_id`, p.`category_id`, p.`comment_text`,
            p.`comment_created`, p.`comment_edited`, p.`comment_deleted`,
            p.`comment_reply_to`, p.`comment_pinned`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_comments_posts` as p
        LEFT JOIN `msz_users` as u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE `comment_reply_to` = :id
    ');
    $getComments->bindValue('id', $commentId);
    return $getComments->execute() ? $getComments->fetchAll(PDO::FETCH_ASSOC) : [];
}

function comments_post_check_ownership(int $commentId, int $userId): bool
{
    $checkUser = db_prepare('
        SELECT COUNT(`comment_id`) > 0
        FROM `msz_comments_posts`
        WHERE `comment_id` = :comment
        AND `user_id` = :user
    ');
    $checkUser->bindValue('comment', $commentId);
    $checkUser->bindValue('user', $userId);
    return $checkUser->execute() ? (bool)$checkUser->fetchColumn() : false;
}
