<?php
define('MSZ_PERMS_GENERAL', 'general');
define('MSZ_PERMS_USER', 'user');
define('MSZ_PERMS_CHANGELOG', 'changelog');
define('MSZ_PERMS_NEWS', 'news');
define('MSZ_PERMS_FORUM', 'forum');
define('MSZ_PERMS_COMMENTS', 'comments');

define('MSZ_PERM_MODES', [
    MSZ_PERMS_GENERAL, MSZ_PERMS_USER, MSZ_PERMS_CHANGELOG,
    MSZ_PERMS_NEWS, MSZ_PERMS_FORUM, MSZ_PERMS_COMMENTS,
]);

define('MSZ_PERMS_ALLOW', 'allow');
define('MSZ_PERMS_DENY', 'deny');

function perms_get_keys(array $modes = MSZ_PERM_MODES): array
{
    $perms = [];

    foreach ($modes as $mode) {
        $perms[] = perms_get_key($mode, MSZ_PERMS_ALLOW);
        $perms[] = perms_get_key($mode, MSZ_PERMS_DENY);
    }

    return $perms;
}

function perms_create(array $modes = MSZ_PERM_MODES): array
{
    return array_fill_keys(perms_get_keys($modes), 0);
}

function perms_get_key(string $prefix, string $suffix): string
{
    return $prefix . '_perms_' . $suffix;
}

function perms_get_select(array $modes = MSZ_PERM_MODES, string $allow = MSZ_PERMS_ALLOW, string $deny = MSZ_PERMS_DENY): string
{
    $select = '';

    if (empty($select)) {
        foreach ($modes as $mode) {
            $select .= sprintf(
                '(BIT_OR(`%1$s_perms_%2$s`) &~ BIT_OR(`%1$s_perms_%3$s`)) AS `%1$s`,',
                $mode, $allow, $deny
            );
        }

        $select = substr($select, 0, -1);
    }

    return $select;
}

function perms_get_blank(array $modes = MSZ_PERM_MODES): array
{
    return array_fill_keys($modes, 0);
}

function perms_get_user(int $user): array
{
    if ($user < 1) {
        return perms_get_blank();
    }

    static $memo = [];

    if (array_key_exists($user, $memo)) {
        return $memo[$user];
    }

    $getPerms = db_prepare(sprintf(
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
    $getPerms->bindValue('user_id_1', $user);
    $getPerms->bindValue('user_id_2', $user);

    return $memo[$user] = db_fetch($getPerms);
}

function perms_delete_user(int $user): bool
{
    if ($user < 1) {
        return false;
    }

    $deletePermissions = db_prepare('
        DELETE FROM `msz_permissions`
        WHERE `role_id` IS NULL
        AND `user_id` = :user_id
    ');
    $deletePermissions->bindValue('user_id', $user);
    return $deletePermissions->execute();
}

function perms_get_role(int $role): array
{
    if ($role < 1) {
        return perms_get_blank();
    }

    static $memo = [];

    if (array_key_exists($role, $memo)) {
        return $memo[$role];
    }

    $getPerms = db_prepare(sprintf(
        '
            SELECT %s
            FROM `msz_permissions`
            WHERE `role_id` = :role_id
            AND `user_id` IS NULL
        ',
        perms_get_select()
    ));
    $getPerms->bindValue('role_id', $role);

    return $memo[$role] = db_fetch($getPerms);
}

function perms_get_user_raw(int $user): array
{
    if ($user < 1) {
        return perms_create();
    }

    $getPerms = db_prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` = :user_id
        AND `role_id` IS NULL
    ', implode('`, `', perms_get_keys())));
    $getPerms->bindValue('user_id', $user);
    $perms = db_fetch($getPerms);

    if (empty($perms)) {
        return perms_create();
    }

    return $perms;
}

function perms_set_user_raw(int $user, array $perms): bool
{
    if ($user < 1) {
        return false;
    }

    $realPerms = perms_create();
    $permKeys = array_keys($realPerms);

    foreach ($permKeys as $perm) {
        $realPerms[$perm] = (int)($perms[$perm] ?? 0);
    }

    $setPermissions = db_prepare(sprintf(
        '
            REPLACE INTO `msz_permissions`
                (`role_id`, `user_id`, `%s`)
            VALUES
                (NULL, :user_id, :%s)
        ',
        implode('`, `', $permKeys),
        implode(', :', $permKeys)
    ));
    $setPermissions->bindValue('user_id', $user);

    foreach ($realPerms as $key => $value) {
        $setPermissions->bindValue($key, $value);
    }

    return $setPermissions->execute();
}

function perms_get_role_raw(int $role): array
{
    if ($role < 1) {
        return perms_create();
    }

    $getPerms = db_prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` IS NULL
        AND `role_id` = :role_id
    ', implode('`, `', perms_get_keys())));
    $getPerms->bindValue('role_id', $role);
    $perms = db_fetch($getPerms);

    if (empty($perms)) {
        return perms_create();
    }

    return $perms;
}

function perms_check(?int $perms, ?int $perm, bool $strict = false): bool
{
    $and = ($perms ?? 0) & ($perm ?? 0);
    return $strict ? $and === $perm : $and > 0;
}

function perms_check_user(string $prefix, ?int $userId, int $perm, bool $strict = false): bool
{
    return $userId > 0 && perms_check(perms_get_user($userId)[$prefix] ?? 0, $perm, $strict);
}

function perms_check_bulk(int $perms, array $set, bool $strict = false): array
{
    foreach ($set as $key => $perm) {
        $set[$key] = perms_check($perms, $perm, $strict);
    }

    return $set;
}

function perms_check_user_bulk(string $prefix, ?int $userId, array $set, bool $strict = false): array
{
    $perms = perms_get_user($userId)[$prefix] ?? 0;
    return perms_check_bulk($perms, $set, $strict);
}
