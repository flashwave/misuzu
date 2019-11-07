<?php
require_once 'Users/validation.php';

define('MSZ_PERM_COMMENTS_CREATE', 1);
//define('MSZ_PERM_COMMENTS_EDIT_OWN', 1 << 1);
//define('MSZ_PERM_COMMENTS_EDIT_ANY', 1 << 2);
define('MSZ_PERM_COMMENTS_DELETE_OWN', 1 << 3);
define('MSZ_PERM_COMMENTS_DELETE_ANY', 1 << 4);
define('MSZ_PERM_COMMENTS_PIN', 1 << 5);
define('MSZ_PERM_COMMENTS_LOCK', 1 << 6);
define('MSZ_PERM_COMMENTS_VOTE', 1 << 7);

define('MSZ_COMMENTS_VOTE_INDIFFERENT', 0);
define('MSZ_COMMENTS_VOTE_LIKE', 1);
define('MSZ_COMMENTS_VOTE_DISLIKE', -1);
define('MSZ_COMMENTS_VOTE_TYPES', [
    MSZ_COMMENTS_VOTE_INDIFFERENT,
    MSZ_COMMENTS_VOTE_LIKE,
    MSZ_COMMENTS_VOTE_DISLIKE,
]);

// gets parsed on post
define('MSZ_COMMENTS_MARKUP_USERNAME', '#\B(?:@{1}(' . MSZ_USERNAME_REGEX . '))#u');

// gets parsed on fetch
define('MSZ_COMMENTS_MARKUP_USER_ID', '#\B(?:@{2}([0-9]+))#u');

function comments_vote_type_valid(int $voteType): bool {
    return in_array($voteType, MSZ_COMMENTS_VOTE_TYPES, true);
}

function comments_parse_for_store(string $text): string {
    return preg_replace_callback(MSZ_COMMENTS_MARKUP_USERNAME, function ($matches) {
        return ($userId = user_id_from_username($matches[1])) < 1
            ? $matches[0]
            : "@@{$userId}";
    }, $text);
}

function comments_parse_for_display(string $text): string {
    $text = preg_replace_callback(
        '/(^|[\n ])([\w]*?)([\w]*?:\/\/[\w]+[^ \,\"\n\r\t<]*)/is',
        function ($matches) {
            $matches[0] = trim($matches[0]);
            $url = parse_url($matches[0]);

            if(empty($url['scheme']) || !in_array(mb_strtolower($url['scheme']), ['http', 'https'], true)) {
                return $matches[0];
            }

            return sprintf(' <a href="%1$s" class="link" target="_blank" rel="noreferrer noopener">%1$s</a>', $matches[0]);
        },
        $text
    );

    $text = preg_replace_callback(MSZ_COMMENTS_MARKUP_USER_ID, function ($matches) {
        $getInfo = \Misuzu\DB::prepare('
            SELECT
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE `user_id` = :user_id
        ');
        $getInfo->bind('user_id', $matches[1]);
        $info = $getInfo->fetch();

        if(empty($info)) {
            return $matches[0];
        }

        return sprintf(
            '<a href="%s" class="comment__mention", style="%s">@%s</a>',
            url('user-profile', ['user' => $info['user_id']]),
            html_colour($info['user_colour']),
            $info['username']
        );
    }, $text);

    return $text;
}

// usually this is not how you're suppose to handle permission checking,
// but in the context of comments this is fine since the same shit is used
// for every comment section.
function comments_get_perms(int $userId): array {
    return perms_check_user_bulk(MSZ_PERMS_COMMENTS, $userId, [
        'can_comment' => MSZ_PERM_COMMENTS_CREATE,
        'can_delete' => MSZ_PERM_COMMENTS_DELETE_OWN | MSZ_PERM_COMMENTS_DELETE_ANY,
        'can_delete_any' => MSZ_PERM_COMMENTS_DELETE_ANY,
        'can_pin' => MSZ_PERM_COMMENTS_PIN,
        'can_lock' => MSZ_PERM_COMMENTS_LOCK,
        'can_vote' => MSZ_PERM_COMMENTS_VOTE,
    ]);
}

function comments_pin_status(int $comment, bool $mode): ?string {
    if($comment < 1) {
        return false;
    }

    $status = $mode ? date('Y-m-d H:i:s') : null;

    $setPinStatus = \Misuzu\DB::prepare('
        UPDATE `msz_comments_posts`
        SET `comment_pinned` = :status
        WHERE `comment_id` = :comment
        AND `comment_reply_to` IS NULL
    ');
    $setPinStatus->bind('comment', $comment);
    $setPinStatus->bind('status', $status);

    return $setPinStatus->execute() ? $status : null;
}

function comments_vote_add(int $comment, int $user, int $vote = MSZ_COMMENTS_VOTE_INDIFFERENT): bool {
    if(!comments_vote_type_valid($vote)) {
        return false;
    }

    $setVote = \Misuzu\DB::prepare('
        REPLACE INTO `msz_comments_votes`
            (`comment_id`, `user_id`, `comment_vote`)
        VALUES
            (:comment, :user, :vote)
    ');
    $setVote->bind('comment', $comment);
    $setVote->bind('user', $user);
    $setVote->bind('vote', $vote);
    return $setVote->execute();
}

function comments_votes_get(int $commentId): array {
    $getVotes = \Misuzu\DB::prepare(sprintf(
        '
            SELECT :id as `id`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_comments_votes`
                WHERE `comment_id` = `id`
                AND `comment_vote` = %1$d
            ) as `likes`,
            (
                SELECT COUNT(`user_id`)
                FROM `msz_comments_votes`
                WHERE `comment_id` = `id`
                AND `comment_vote` = %2$d
            ) as `dislikes`
        ',
        MSZ_COMMENTS_VOTE_LIKE,
        MSZ_COMMENTS_VOTE_DISLIKE
    ));
    $getVotes->bind('id', $commentId);
    return $getVotes->fetch();
}

function comments_category_create(string $name): array {
    $create = \Misuzu\DB::prepare('
        INSERT INTO `msz_comments_categories`
            (`category_name`)
        VALUES
            (LOWER(:name))
    ');
    $create->bind('name', $name);
    return $create->execute()
        ? comments_category_info(\Misuzu\DB::lastId(), false)
        : [];
}

function comments_category_lock(int $category, bool $lock): void {
    $setLock = \Misuzu\DB::prepare('
        UPDATE `msz_comments_categories`
        SET `category_locked` = IF(:lock, NOW(), NULL)
        WHERE `category_id` = :category
    ');
    $setLock->bind('category', $category);
    $setLock->bind('lock', $lock);
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

function comments_category_info($category, bool $createIfNone = false): array {
    if(is_int($category)) {
        $getCategory = \Misuzu\DB::prepare(MSZ_COMMENTS_CATEGORY_INFO_ID);
        $createIfNone = false;
    } elseif(is_string($category)) {
        $getCategory = \Misuzu\DB::prepare(MSZ_COMMENTS_CATEGORY_INFO_NAME);
    } else {
        return [];
    }

    $getCategory->bind('category', $category);
    $categoryInfo = $getCategory->fetch();
    return $categoryInfo
        ? $categoryInfo
        : (
            $createIfNone
                ? comments_category_create($category)
                : []
        );
}

define('MSZ_COMMENTS_CATEGORY_QUERY', sprintf(
    '
        SELECT
            p.`comment_id`, p.`comment_text`, p.`comment_reply_to`,
            p.`comment_created`, p.`comment_pinned`, p.`comment_deleted`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`,
            (
                SELECT COUNT(`comment_id`)
                FROM `msz_comments_votes`
                WHERE `comment_id` = p.`comment_id`
                AND `comment_vote` = %1$d
            ) AS `comment_likes`,
            (
                SELECT COUNT(`comment_id`)
                FROM `msz_comments_votes`
                WHERE `comment_id` = p.`comment_id`
                AND `comment_vote` = %2$d
            ) AS `comment_dislikes`,
            (
                SELECT `comment_vote`
                FROM `msz_comments_votes`
                WHERE `comment_id` = p.`comment_id`
                AND `user_id` = :user
            ) AS `comment_user_vote`
        FROM `msz_comments_posts` AS p
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = p.`user_id`
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
        WHERE p.`category_id` = :category
        %%1$s
        ORDER BY p.`comment_deleted` ASC, p.`comment_pinned` DESC, p.`comment_id` %%2$s
    ',
    MSZ_COMMENTS_VOTE_LIKE,
    MSZ_COMMENTS_VOTE_DISLIKE
));

// The $parent param should never be used outside of this function itself and should always remain the last of the list.
function comments_category_get(int $category, int $user, ?int $parent = null): array {
    $isParent = $parent === null;
    $getComments = \Misuzu\DB::prepare(sprintf(
        MSZ_COMMENTS_CATEGORY_QUERY,
        $isParent ? 'AND p.`comment_reply_to` IS NULL' : 'AND p.`comment_reply_to` = :parent',
        $isParent ? 'DESC' : 'ASC'
    ));

    if(!$isParent) {
        $getComments->bind('parent', $parent);
    }

    $getComments->bind('user', $user);
    $getComments->bind('category', $category);
    $comments = $getComments->fetchAll();

    $commentsCount = count($comments);
    for($i = 0; $i < $commentsCount; $i++) {
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
    if($parse) {
        $text = comments_parse_for_store($text);
    }

    $create = \Misuzu\DB::prepare('
        INSERT INTO `msz_comments_posts`
            (`user_id`, `category_id`, `comment_text`, `comment_pinned`, `comment_reply_to`)
        VALUES
            (:user, :category, :text, IF(:pin, NOW(), NULL), :reply)
    ');
    $create->bind('user', $user);
    $create->bind('category', $category);
    $create->bind('text', $text);
    $create->bind('pin', $pinned ? 1 : 0);
    $create->bind('reply', $reply < 1 ? null : $reply);
    return $create->execute() ? \Misuzu\DB::lastId() : 0;
}

function comments_post_delete(int $commentId, bool $delete = true): bool {
    $deleteComment = \Misuzu\DB::prepare('
        UPDATE `msz_comments_posts`
        SET `comment_deleted` = IF(:del, NOW(), NULL)
        WHERE `comment_id` = :id
    ');
    $deleteComment->bind('id', $commentId);
    $deleteComment->bind('del', $delete ? 1 : 0);
    return $deleteComment->execute();
}

function comments_post_get(int $commentId, bool $parse = true): array {
    $fetch = \Misuzu\DB::prepare('
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
    $fetch->bind('id', $commentId);
    $comment = $fetch->fetch();

    if($comment && $parse) {
        $comment['comment_html'] = nl2br(comments_parse_for_display(htmlentities($comment['comment_text'])));
    }

    return $comment;
}

function comments_post_exists(int $commentId): bool {
    $fetch = \Misuzu\DB::prepare('
        SELECT COUNT(`comment_id`) > 0
        FROM `msz_comments_posts`
        WHERE `comment_id` = :id
    ');
    $fetch->bind('id', $commentId);
    return (bool)$fetch->fetchColumn();
}

function comments_post_replies(int $commentId): array {
    $getComments = \Misuzu\DB::prepare('
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
    $getComments->bind('id', $commentId);
    return $getComments->fetchAll();
}
