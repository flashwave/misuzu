<?php
define('MSZ_PERMS_GENERAL', 'general');
define('MSZ_PERM_GENERAL_CAN_MANAGE',       0x00000001);
define('MSZ_PERM_GENERAL_VIEW_LOGS',        0x00000002);
define('MSZ_PERM_GENERAL_MANAGE_EMOTES',    0x00000004);
define('MSZ_PERM_GENERAL_MANAGE_CONFIG',    0x00000008);
define('MSZ_PERM_GENERAL_IS_TESTER',        0x00000010);
define('MSZ_PERM_GENERAL_MANAGE_BLACKLIST', 0x00000020);

define('MSZ_PERMS_USER', 'user');
define('MSZ_PERM_USER_EDIT_PROFILE',        0x00000001);
define('MSZ_PERM_USER_CHANGE_AVATAR',       0x00000002);
define('MSZ_PERM_USER_CHANGE_BACKGROUND',   0x00000004);
define('MSZ_PERM_USER_EDIT_ABOUT',          0x00000008);
define('MSZ_PERM_USER_EDIT_BIRTHDATE',      0x00000010);
define('MSZ_PERM_USER_EDIT_SIGNATURE',      0x00000020);
define('MSZ_PERM_USER_MANAGE_USERS',        0x00100000);
define('MSZ_PERM_USER_MANAGE_ROLES',        0x00200000);
define('MSZ_PERM_USER_MANAGE_PERMS',        0x00400000);
define('MSZ_PERM_USER_MANAGE_REPORTS',      0x00800000);
define('MSZ_PERM_USER_MANAGE_WARNINGS',     0x01000000);
//define('MSZ_PERM_USER_MANAGE_BLACKLISTS', 0x02000000); // Replaced with MSZ_PERM_MANAGE_BLACKLIST

define('MSZ_PERMS_CHANGELOG', 'changelog');
define('MSZ_PERM_CHANGELOG_MANAGE_CHANGES',   0x00000001);
define('MSZ_PERM_CHANGELOG_MANAGE_TAGS',      0x00000002);
//define('MSZ_PERM_CHANGELOG_MANAGE_ACTIONS', 0x00000004); Deprecated, actions are hardcoded now

define('MSZ_PERMS_NEWS', 'news');
define('MSZ_PERM_NEWS_MANAGE_POSTS',      0x00000001);
define('MSZ_PERM_NEWS_MANAGE_CATEGORIES', 0x00000002);

define('MSZ_PERMS_FORUM', 'forum');
define('MSZ_PERM_FORUM_MANAGE_FORUMS',    0x00000001);
define('MSZ_PERM_FORUM_VIEW_LEADERBOARD', 0x00000002);

define('MSZ_PERMS_COMMENTS', 'comments');
define('MSZ_PERM_COMMENTS_CREATE',     0x00000001);
//define('MSZ_PERM_COMMENTS_EDIT_OWN', 0x00000002);
//define('MSZ_PERM_COMMENTS_EDIT_ANY', 0x00000004);
define('MSZ_PERM_COMMENTS_DELETE_OWN', 0x00000008);
define('MSZ_PERM_COMMENTS_DELETE_ANY', 0x00000010);
define('MSZ_PERM_COMMENTS_PIN',        0x00000020);
define('MSZ_PERM_COMMENTS_LOCK',       0x00000040);
define('MSZ_PERM_COMMENTS_VOTE',       0x00000080);

define('MSZ_PERM_MODES', [
    MSZ_PERMS_GENERAL, MSZ_PERMS_USER, MSZ_PERMS_CHANGELOG,
    MSZ_PERMS_NEWS, MSZ_PERMS_FORUM, MSZ_PERMS_COMMENTS,
]);

define('MSZ_PERMS_ALLOW', 'allow');
define('MSZ_PERMS_DENY', 'deny');

function perms_get_keys(array $modes = MSZ_PERM_MODES): array {
    $perms = [];

    foreach($modes as $mode) {
        $perms[] = perms_get_key($mode, MSZ_PERMS_ALLOW);
        $perms[] = perms_get_key($mode, MSZ_PERMS_DENY);
    }

    return $perms;
}

function perms_create(array $modes = MSZ_PERM_MODES): array {
    return array_fill_keys(perms_get_keys($modes), 0);
}

function perms_get_key(string $prefix, string $suffix): string {
    return $prefix . '_perms_' . $suffix;
}

function perms_get_select(array $modes = MSZ_PERM_MODES, string $allow = MSZ_PERMS_ALLOW, string $deny = MSZ_PERMS_DENY): string {
    $select = '';

    if(empty($select)) {
        foreach($modes as $mode) {
            $select .= sprintf(
                '(BIT_OR(`%1$s_perms_%2$s`) &~ BIT_OR(`%1$s_perms_%3$s`)) AS `%1$s`,',
                $mode, $allow, $deny
            );
        }

        $select = substr($select, 0, -1);
    }

    return $select;
}

function perms_get_blank(array $modes = MSZ_PERM_MODES): array {
    return array_fill_keys($modes, 0);
}

function perms_get_user(int $user): array {
    if($user < 1) {
        return perms_get_blank();
    }

    static $memo = [];

    if(array_key_exists($user, $memo)) {
        return $memo[$user];
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT %s
            FROM `msz_permissions`
            WHERE (`user_id` = :user_id_1 AND `role_id` IS NULL)
            OR (
                `user_id` IS NULL
                AND `role_id` IN (
                    SELECT `role_id`
                    FROM `msz_user_roles`
                    WHERE `user_id` = :user_id_2
                )
            )
        ',
        perms_get_select()
    ));
    $getPerms->bind('user_id_1', $user);
    $getPerms->bind('user_id_2', $user);

    return $memo[$user] = $getPerms->fetch();
}

function perms_delete_user(int $user): bool {
    if($user < 1) {
        return false;
    }

    $deletePermissions = \Misuzu\DB::prepare('
        DELETE FROM `msz_permissions`
        WHERE `role_id` IS NULL
        AND `user_id` = :user_id
    ');
    $deletePermissions->bind('user_id', $user);
    return $deletePermissions->execute();
}

function perms_get_role(int $role): array {
    if($role < 1) {
        return perms_get_blank();
    }

    static $memo = [];

    if(array_key_exists($role, $memo)) {
        return $memo[$role];
    }

    $getPerms = \Misuzu\DB::prepare(sprintf(
        '
            SELECT %s
            FROM `msz_permissions`
            WHERE `role_id` = :role_id
            AND `user_id` IS NULL
        ',
        perms_get_select()
    ));
    $getPerms->bind('role_id', $role);

    return $memo[$role] = $getPerms->fetch();
}

function perms_get_user_raw(int $user): array {
    if($user < 1) {
        return perms_create();
    }

    $getPerms = \Misuzu\DB::prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` = :user_id
        AND `role_id` IS NULL
    ', implode('`, `', perms_get_keys())));
    $getPerms->bind('user_id', $user);
    $perms = $getPerms->fetch();

    if(empty($perms)) {
        return perms_create();
    }

    return $perms;
}

function perms_set_user_raw(int $user, array $perms): bool {
    if($user < 1) {
        return false;
    }

    $realPerms = perms_create();
    $permKeys = array_keys($realPerms);

    foreach($permKeys as $perm) {
        $realPerms[$perm] = (int)($perms[$perm] ?? 0);
    }

    $setPermissions = \Misuzu\DB::prepare(sprintf(
        '
            REPLACE INTO `msz_permissions`
                (`role_id`, `user_id`, `%s`)
            VALUES
                (NULL, :user_id, :%s)
        ',
        implode('`, `', $permKeys),
        implode(', :', $permKeys)
    ));
    $setPermissions->bind('user_id', $user);

    foreach($realPerms as $key => $value) {
        $setPermissions->bind($key, $value);
    }

    return $setPermissions->execute();
}

function perms_get_role_raw(int $role): array {
    if($role < 1) {
        return perms_create();
    }

    $getPerms = \Misuzu\DB::prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` IS NULL
        AND `role_id` = :role_id
    ', implode('`, `', perms_get_keys())));
    $getPerms->bind('role_id', $role);
    $perms = $getPerms->fetch();

    if(empty($perms)) {
        return perms_create();
    }

    return $perms;
}

function perms_check(?int $perms, ?int $perm, bool $strict = false): bool {
    $and = ($perms ?? 0) & ($perm ?? 0);
    return $strict ? $and === $perm : $and > 0;
}

function perms_check_user(string $prefix, ?int $userId, int $perm, bool $strict = false): bool {
    return $userId > 0 && perms_check(perms_get_user($userId)[$prefix] ?? 0, $perm, $strict);
}

function perms_check_bulk(int $perms, array $set, bool $strict = false): array {
    foreach($set as $key => $perm) {
        $set[$key] = perms_check($perms, $perm, $strict);
    }

    return $set;
}

function perms_check_user_bulk(string $prefix, ?int $userId, array $set, bool $strict = false): array {
    $perms = perms_get_user($userId)[$prefix] ?? 0;
    return perms_check_bulk($perms, $set, $strict);
}
