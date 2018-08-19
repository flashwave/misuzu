<?php
use Misuzu\Database;

define('MSZ_FORUM_PERMS_GENERAL', 'forum');

define('MSZ_FORUM_PERM_MODES', [
    MSZ_FORUM_PERMS_GENERAL,
]);

function forum_perms_get_keys(): array
{
    $perms = [];

    foreach (MSZ_FORUM_PERM_MODES as $mode) {
        foreach (MSZ_PERM_SETS as $set) {
            $perms[] = perms_get_key($mode, $set);
        }
    }

    return $perms;
}

function forum_perms_create(): int
{
    $perms = [];

    foreach (forum_perms_get_keys() as $key) {
        $perms[$key] = 0;
    }

    return $perms;
}

function forum_perms_get_user_sql(
    string $prefix,
    string $forum = ':perm_forum_id',
    string $user_for_user = ':perm_user_id_1',
    string $user_for_role = ':perm_user_id_2'
): string {
    return "
        SELECT BIT_OR(`{$prefix}_perms_allow`) &~ BIT_OR(`{$prefix}_perms_deny`)
        FROM `msz_forum_permissions`
        WHERE (
            `forum_id` = {$forum}
            OR `forum_id` IS NULL
        )
        AND (
            (`user_id` IS NULL AND `role_id` IS NULL)
            OR (`user_id` = {$user_for_user} AND `role_id` IS NULL)
            OR (
                `user_id` IS NULL
                AND `role_id` IN (
                    SELECT `role_id`
                    FROM `msz_user_roles`
                    WHERE `user_id` = {$user_for_role}
                )
            )
        )
    ";
}

function forum_perms_get_user(string $prefix, int $forum, int $user): int
{
    if (!in_array($prefix, MSZ_FORUM_PERM_MODES) || $user < 1) {
        return 0;
    } elseif ($user === 1) {
        //return 0x7FFFFFFF;
    }

    $getPerms = Database::prepare(forum_perms_get_user_sql($prefix));
    $getPerms->bindValue('perm_forum_id', $forum);
    $getPerms->bindValue('perm_user_id_1', $user);
    $getPerms->bindValue('perm_user_id_2', $user);
    return $getPerms->execute() ? (int)$getPerms->fetchColumn() : 0;
}

function forum_perms_get_role(string $prefix, int $forum, int $role): int
{
    if (!in_array($prefix, MSZ_FORUM_PERM_MODES) || $role < 1) {
        return 0;
    }

    $getPerms = Database::prepare("
        SELECT `{$prefix}_perms_allow` &~ `{$prefix}_perms_deny`
        FROM `msz_forum_permissions`
        WHERE (
            `forum_id` = :forum_id
            OR `forum_id` IS NULL
        )
        AND `role_id` = :role_id
        AND `user_id` IS NULL
    ");
    $getPerms->bindValue('forum_id', $forum);
    $getPerms->bindValue('role_id', $role);
    return $getPerms->execute() ? (int)$getPerms->fetchColumn() : 0;
}

function forum_perms_get_user_raw(?int $forum, int $user): array
{
    $emptyPerms = forum_perms_create();

    if ($user < 1) {
        return $emptyPerms;
    }

    $getPerms = Database::prepare(sprintf('
        SELECT
            `' . implode('`, `', forum_perms_get_keys()) . '`
        FROM `msz_forum_permissions`
        WHERE `forum_id` %s
        AND `user_id` = :user_id
        AND `role_id` IS NULL
    ', $forum === null ? 'IS NULL' : '= :forum_id'));

    if ($forum !== null) {
        $getPerms->bindValue('forum_id', $forum);
    }

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

function forum_perms_get_role_raw(?int $forum, int $role): array
{
    $emptyPerms = forum_perms_create();

    if ($role < 1) {
        return $emptyPerms;
    }

    $getPerms = Database::prepare(sprintf('
        SELECT
            `' . implode('`, `', forum_perms_get_keys()) . '`
        FROM `msz_forum_permissions`
        WHERE `forum_id` %s
        AND `user_id` IS NULL
        AND `role_id` = :role_id
    ', $forum === null ? 'IS NULL' : '= :forum_id'));

    if ($forum !== null) {
        $getPerms->bindValue('forum_id', $forum);
    }

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
