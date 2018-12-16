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
define('MSZ_PERMS_OVERRIDE', 'override');

define('MSZ_PERM_SETS', [
    MSZ_PERMS_ALLOW, MSZ_PERMS_DENY, MSZ_PERMS_OVERRIDE,
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
    $overrideKey = perms_get_key($prefix, MSZ_PERMS_OVERRIDE);

    $getPerms = db_prepare("
        SELECT BIT_OR(_pu.`{$allowKey}`) &~ BIT_OR(_pu.`{$denyKey}`) | (
            (
                SELECT BIT_OR(_pr.`{$allowKey}`) &~ BIT_OR(_pr.`{$denyKey}`) | (
                    (
                        SELECT BIT_OR(_pg.`{$allowKey}`) &~ BIT_OR(_pg.`{$denyKey}`)
                        FROM `msz_permissions` as _pg
                        WHERE _pg.`user_id` IS NULL
                        AND _pg.`role_id` IS NULL
                    ) &~ BIT_OR(_pr.`{$overrideKey}`)
                )
                FROM `msz_permissions` as _pr
                WHERE _pr.`user_id` IS NULL
                AND _pr.`role_id` IN (
                    SELECT _prr.`role_id`
                    FROM `msz_user_roles` as _pru
                    LEFT JOIN `msz_roles` as _prr
                    ON _prr.`role_id` = _pru.`role_id`
                    WHERE _pru.`user_id` = :user_id_2
                    ORDER BY _prr.`role_hierarchy`
                )
            ) &~ BIT_OR(_pu.`{$overrideKey}`)
        )
        FROM `msz_permissions` as _pu
        WHERE _pu.`user_id` = :user_id_1
        AND _pu.`role_id` IS NULL
    ");
    $getPerms->bindValue('user_id_1', $user);
    $getPerms->bindValue('user_id_2', $user);
    return $getPerms->execute() ? (int)$getPerms->fetchColumn() : 0;
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
    $emptyPerms = perms_create();

    if ($user < 1) {
        return $emptyPerms;
    }

    $getPerms = db_prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` = :user_id
        AND `role_id` IS NULL
    ', implode('`, `', perms_get_keys())));
    $getPerms->bindValue('user_id', $user);

    if (!$getPerms->execute()) {
        return $emptyPerms;
    }

    $perms = $getPerms->fetch(PDO::FETCH_ASSOC);

    if (!$perms) {
        return $emptyPerms;
    }

    return $perms;
}

function perms_get_role_raw(int $role): array
{
    $emptyPerms = perms_create();

    if ($role < 1) {
        return $emptyPerms;
    }

    $getPerms = db_prepare(sprintf('
        SELECT `%s`
        FROM `msz_permissions`
        WHERE `user_id` IS NULL
        AND `role_id` = :role_id
    ', implode('`, `', perms_get_keys())));
    $getPerms->bindValue('role_id', $role);

    if (!$getPerms->execute()) {
        return $emptyPerms;
    }

    $perms = $getPerms->fetch(PDO::FETCH_ASSOC);

    if (!$perms) {
        return $emptyPerms;
    }

    return $perms;
}

function perms_check(int $perms, int $perm): bool
{
    return ($perms & $perm) > 0;
}
