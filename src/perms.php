<?php
use Misuzu\Database;

define('MSZ_PERMS_USER', 'user');
define('MSZ_PERMS_CHANGELOG', 'changelog');

$_msz_perms_cache = [];

function perms_construct_cache_key(string $prefix, string $mode, int $pid): string
{
    return $prefix . '_' . $mode . '_' . $pid;
}

function perms_get_cache(string $prefix, string $mode, int $pid): int
{
    global $_msz_perms_cache;
    return $_msz_perms_cache[perms_construct_cache_key($prefix, $mode, $pid)];
}

function perms_set_cache(string $prefix, string $mode, int $pid, int $perms): int
{
    global $_msz_perms_cache;
    return $_msz_perms_cache[perms_construct_cache_key($prefix, $mode, $pid)] = $perms;
}

function perms_is_cached(string $prefix, string $mode, int $pid): bool
{
    global $_msz_perms_cache;
    return array_key_exists(perms_construct_cache_key($prefix, $mode, $pid), $_msz_perms_cache);
}

function perms_get_user(string $prefix, int $user): int
{
    if ($user < 1) {
        return 0;
    } elseif ($user === 1) {
        return 0x7FFFFFFF;
    }

    if (perms_is_cached($prefix, 'user', $user)) {
        return perms_get_cache($prefix, 'user', $user);
    }

    $permsAllow = 0;
    $permsDeny = 0;

    $getPerms = Database::connection()->prepare("
        SELECT `{$prefix}_perms_allow` as `allow`, `{$prefix}_perms_deny` as `deny`
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
    ");
    $getPerms->bindValue('user_id_1', $user);
    $getPerms->bindValue('user_id_2', $user);
    $perms = $getPerms->execute() ? $getPerms->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($perms as $perm) {
        $permsAllow |= $perm['allow'];
        $permsDeny |= $perm['deny'];
    }

    return perms_set_cache($prefix, 'user', $user, $permsAllow &~ $permsDeny);
}

function perms_get_role(string $prefix, int $role): int
{
    if ($role < 1) {
        return 0;
    }

    if (perms_is_cached($prefix, 'role', $user)) {
        return perms_get_cache($prefix, 'role', $user);
    }

    $getPerms = Database::connection()->prepare("
        SELECT `{$prefix}_perms_allow` &~ `{$prefix}_perms_deny`
        FROM `msz_permissions`
        WHERE `role_id` = :role_id
        AND `user_id` IS NULL
    ");
    $getPerms->bindValue('role_id', $role);
    return perms_set_cache($prefix, 'role', $role, $getPerms->execute() ? (int)$getPerms->fetchColumn() : 0);
}

function perms_check(int $perms, int $perm): bool
{
    return ($perms & $perm) > 0;
}
