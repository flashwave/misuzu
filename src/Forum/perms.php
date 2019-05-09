<?php
define('MSZ_FORUM_PERMS_GENERAL', 'forum');

define('MSZ_FORUM_PERM_MODES', [
    MSZ_FORUM_PERMS_GENERAL,
]);

function forum_perms_get_user(?int $forum, int $user): array
{
    $perms = perms_get_blank(MSZ_FORUM_PERM_MODES);

    if ($user < 0 || $forum < 0) {
        return $perms;
    }

    static $memo = [];
    $memoId = "{$forum}-{$user}";

    if (array_key_exists($memoId, $memo)) {
        return $memo[$memoId];
    }

    if ($forum > 0) {
        $perms = forum_perms_get_user(
            forum_get_parent_id($forum),
            $user
        );
    }

    $getPerms = db_prepare(sprintf(
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
    $getPerms->bindValue('forum_id', $forum);
    $getPerms->bindValue('user_id_1', $user);
    $getPerms->bindValue('user_id_2', $user);

    return $memo[$memoId] = array_bit_or($perms, db_fetch($getPerms));
}

function forum_perms_get_role(?int $forum, int $role): array
{
    $perms = perms_get_blank(MSZ_FORUM_PERM_MODES);

    if ($role < 1 || $forum < 0) {
        return $perms;
    }

    static $memo = [];
    $memoId = "{$forum}-{$role}";

    if (array_key_exists($memoId, $memo)) {
        return $memo[$memoId];
    }

    if ($forum > 0) {
        $perms = forum_perms_get_role(
            forum_get_parent_id($forum),
            $role
        );
    }

    $getPerms = db_prepare(sprintf(
        '
            SELECT %s
            FROM `msz_forum_permissions`
            WHERE (`forum_id` = :forum_id OR `forum_id` IS NULL)
            AND `role_id` = :role_id
            AND `user_id` IS NULL
        ',
        perms_get_select(MSZ_FORUM_PERM_MODES)
    ));
    $getPerms->bindValue('forum_id', $forum);
    $getPerms->bindValue('role_id', $role);

    return $memo[$memoId] = array_bit_or($perms, db_fetch($getPerms));
}

function forum_perms_get_user_raw(?int $forum, int $user): array
{
    if ($user < 1) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    $getPerms = db_prepare(sprintf(
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

    if ($forum !== null) {
        $getPerms->bindValue('forum_id', $forum);
    }

    $getPerms->bindValue('user_id', $user);
    $perms = db_fetch($getPerms);

    if (empty($perms)) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    return $perms;
}

function forum_perms_get_role_raw(?int $forum, ?int $role): array
{
    if ($role < 1 && $role !== null) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    $getPerms = db_prepare(sprintf(
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

    if ($forum !== null) {
        $getPerms->bindValue('forum_id', $forum);
    }

    if ($role !== null) {
        $getPerms->bindValue('role_id', $role);
    }

    $perms = db_fetch($getPerms);

    if (empty($perms)) {
        return perms_create(MSZ_FORUM_PERM_MODES);
    }

    return $perms;
}

function forum_perms_check_user(string $prefix, ?int $forumId, ?int $userId, int $perm, bool $strict = false): bool
{
    return perms_check(forum_perms_get_user($forumId, $userId)[$prefix] ?? 0, $perm, $strict);
}
