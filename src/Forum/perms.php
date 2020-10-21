<?php
define('MSZ_FORUM_PERMS_GENERAL', 'forum');

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

define('MSZ_FORUM_PERM_MODES', [
    MSZ_FORUM_PERMS_GENERAL,
]);

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

function forum_perms_get_user(?int $forum, int $user): array {
    $perms = perms_get_blank(MSZ_FORUM_PERM_MODES);

    if($user < 0 || $forum < 0) {
        return $perms;
    }

    static $memo = [];
    $memoId = "{$forum}-{$user}";

    if(array_key_exists($memoId, $memo)) {
        return $memo[$memoId];
    }

    if($forum > 0) {
        $perms = forum_perms_get_user(
            forum_get_parent_id($forum),
            $user
        );
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT %s
            FROM `msz_forum_permissions`
            WHERE (`forum_id` = :forum_id OR `forum_id` IS NULL)
            AND (
                (`user_id` IS NULL AND `role_id` IS NULL)
                OR (`user_id` = :user_id_1 AND `role_id` IS NULL)
                OR (
                    `user_id` IS NULL
                    AND `role_id` IN (
                        SELECT `role_id`
                        FROM `msz_user_roles`
                        WHERE `user_id` = :user_id_2
                    )
                )
            )
        ',
        perms_get_select(MSZ_FORUM_PERM_MODES)
    ));
    $getPerms->bind('forum_id', $forum);
    $getPerms->bind('user_id_1', $user);
    $getPerms->bind('user_id_2', $user);

    return $memo[$memoId] = array_bit_or($perms, $getPerms->fetch());
}

function forum_perms_get_role(?int $forum, int $role): array {
    $perms = perms_get_blank(MSZ_FORUM_PERM_MODES);

    if($role < 1 || $forum < 0) {
        return $perms;
    }

    static $memo = [];
    $memoId = "{$forum}-{$role}";

    if(array_key_exists($memoId, $memo)) {
        return $memo[$memoId];
    }

    if($forum > 0) {
        $perms = forum_perms_get_role(
            forum_get_parent_id($forum),
            $role
        );
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT %s
            FROM `msz_forum_permissions`
            WHERE (`forum_id` = :forum_id OR `forum_id` IS NULL)
            AND `role_id` = :role_id
            AND `user_id` IS NULL
        ',
        perms_get_select(MSZ_FORUM_PERM_MODES)
    ));
    $getPerms->bind('forum_id', $forum);
    $getPerms->bind('role_id', $role);

    return $memo[$memoId] = array_bit_or($perms, $getPerms->fetch());
}

function forum_perms_get_user_raw(?int $forum, int $user): array {
    if($user < 1) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT `%s`
            FROM `msz_forum_permissions`
            WHERE `forum_id` %s
            AND `user_id` = :user_id
            AND `role_id` IS NULL
        ',
        implode('`, `', perms_get_keys(MSZ_FORUM_PERM_MODES)),
        $forum === null ? 'IS NULL' : '= :forum_id'
    ));

    if($forum !== null) {
        $getPerms->bind('forum_id', $forum);
    }

    $getPerms->bind('user_id', $user);
    $perms = $getPerms->fetch();

    if(empty($perms)) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    return $perms;
}

function forum_perms_get_role_raw(?int $forum, ?int $role): array {
    if($role < 1 && $role !== null) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT `%s`
            FROM `msz_forum_permissions`
            WHERE `forum_id` %s
            AND `user_id` IS NULL
            AND `role_id` %s
        ',
        implode('`, `', perms_get_keys(MSZ_FORUM_PERM_MODES)),
        $forum === null ? 'IS NULL' : '= :forum_id',
        $role === null ? 'IS NULL' : '= :role_id'
    ));

    if($forum !== null) {
        $getPerms->bind('forum_id', $forum);
    }

    if($role !== null) {
        $getPerms->bind('role_id', $role);
    }

    $perms = $getPerms->fetch();

    if(empty($perms)) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    return $perms;
}

function forum_perms_check_user(
    string $prefix,
    ?int $forumId,
    ?int $userId,
    int $perm,
    bool $strict = false
): bool {
    return perms_check(forum_perms_get_user($forumId, $userId)[$prefix] ?? 0, $perm, $strict);
}
