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

define('MSZ_PERM_SETS', [
    MSZ_PERMS_ALLOW, MSZ_PERMS_DENY,
]);

function perms_get_keys(): array
{
    $perms = [];

    foreach (MSZ_PERM_MODES as $mode) {
        foreach (MSZ_PERM_SETS as $set) {
            $perms[] = perms_get_key($mode, $set);
        }
    }

    return $perms;
}

function perms_create(): array
{
    $perms = [];

    foreach (perms_get_keys() as $key) {
        $perms[$key] = 0;
    }

    return $perms;
}

function perms_get_key(string $prefix, string $suffix): string
{
    return $prefix . '_perms_' . $suffix;
}

function perms_get_user(string $prefix, int $user): int
{
    if (!in_array($prefix, MSZ_PERM_MODES) || $user < 0) {
        return 0;
    }

    $allowKey = perms_get_key($prefix, MSZ_PERMS_ALLOW);
    $denyKey = perms_get_key($prefix, MSZ_PERMS_DENY);

    $getPerms = db_prepare("
        SELECT :user_id AS `select_user`, (
            SELECT BIT_OR(`{$allowKey}`) &~ BIT_OR(`{$denyKey}`)
            FROM `msz_permissions`
            WHERE (`user_id` = `select_user` AND `role_id` IS NULL)
            OR (
                `user_id` IS NULL
                AND `role_id` IN (
                    SELECT `role_id`
                    FROM `msz_user_roles`
                    WHERE `user_id` = `select_user`
                )
            )
        )
    ");
    $getPerms->bindValue('user_id', $user);
    return $getPerms->execute() ? (int)$getPerms->fetchColumn(1) : 0;
}

function perms_get_role(string $prefix, int $role): int
{
    if (!in_array($prefix, MSZ_PERM_MODES) || $role < 1) {
        return 0;
    }

    $allowKey = perms_get_key($prefix, MSZ_PERMS_ALLOW);
    $denyKey = perms_get_key($prefix, MSZ_PERMS_DENY);

    $getPerms = db_prepare("
        SELECT `{$allowKey}` &~ `{$denyKey}`
        FROM `msz_permissions`
        WHERE `role_id` = :role_id
        AND `user_id` IS NULL
    ");
    $getPerms->bindValue('role_id', $role);
    return $getPerms->execute() ? (int)$getPerms->fetchColumn() : 0;
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

function perms_check(int $perms, int $perm): bool
{
    return ($perms & $perm) > 0;
}
