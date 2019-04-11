<?php
define('MSZ_PERM_CHANGELOG_MANAGE_CHANGES', 1);
define('MSZ_PERM_CHANGELOG_MANAGE_TAGS', 1 << 1);
//define('MSZ_PERM_CHANGELOG_MANAGE_ACTIONS', 1 << 2); Deprecated, actions are hardcoded now

define('MSZ_CHANGELOG_ACTION_ADD', 1);
define('MSZ_CHANGELOG_ACTION_REMOVE', 2);
define('MSZ_CHANGELOG_ACTION_UPDATE', 3);
define('MSZ_CHANGELOG_ACTION_FIX', 4);
define('MSZ_CHANGELOG_ACTION_IMPORT', 5);
define('MSZ_CHANGELOG_ACTION_REVERT', 6);
define('MSZ_CHANGELOG_ACTIONS', [
    MSZ_CHANGELOG_ACTION_ADD => 'add',
    MSZ_CHANGELOG_ACTION_REMOVE => 'remove',
    MSZ_CHANGELOG_ACTION_UPDATE => 'update',
    MSZ_CHANGELOG_ACTION_FIX => 'fix',
    MSZ_CHANGELOG_ACTION_IMPORT => 'import',
    MSZ_CHANGELOG_ACTION_REVERT => 'revert',
]);

function changelog_action_name(int $action): string
{
    return changelog_action_is_valid($action) ? MSZ_CHANGELOG_ACTIONS[$action] : '';
}

function changelog_action_is_valid(int $action): bool
{
    return array_key_exists($action, MSZ_CHANGELOG_ACTIONS);
}

function changelog_entry_create(int $userId, int $action, string $log, string $text = null): int
{
    if (!changelog_action_is_valid($action)) {
        return -1;
    }

    $createChange = db_prepare('
        INSERT INTO `msz_changelog_changes`
            (`user_id`, `change_action`, `change_log`, `change_text`)
        VALUES
            (:user_id, :action, :change_log, :change_text)
    ');
    $createChange->bindValue('user_id', $userId);
    $createChange->bindValue('action', $action);
    $createChange->bindValue('change_log', $log);
    $createChange->bindValue('change_text', $text);

    return $createChange->execute() ? (int)db_last_insert_id() : 0;
}

define('MSZ_CHANGELOG_GET_QUERY', '
    SELECT
        c.`change_id`, c.`change_log`, c.`change_action`,
        u.`user_id`, u.`username`,
        DATE(`change_created`) AS `change_date`,
        !ISNULL(c.`change_text`) AS `change_has_text`,
        COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`
    FROM `msz_changelog_changes` AS c
    LEFT JOIN `msz_users` AS u
    ON u.`user_id` = c.`user_id`
    LEFT JOIN `msz_roles` AS r
    ON r.`role_id` = u.`display_role`
    WHERE %s
    AND %s
    GROUP BY `change_created`, `change_id`
    ORDER BY `change_created` DESC, `change_id` DESC
    %s
');

function changelog_get_changes(string $date, int $user, int $offset, int $take): array
{
    $hasDate = mb_strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        MSZ_CHANGELOG_GET_QUERY,
        $hasDate ? 'DATE(c.`change_created`) = :date' : '1',
        $hasUser ? 'c.`user_id` = :user' : '1',
        !$hasDate ? 'LIMIT :offset, :take' : ''
    );

    $prep = db_prepare($query);

    if (!$hasDate) {
        $prep->bindValue('offset', $offset);
        $prep->bindValue('take', $take);
    } else {
        $prep->bindValue('date', $date);
    }

    if ($hasUser) {
        $prep->bindValue('user', $user);
    }

    return db_fetch_all($prep);
}

define('CHANGELOG_COUNT_QUERY', '
    SELECT COUNT(`change_id`)
    FROM `msz_changelog_changes`
    WHERE %s
    AND %s
');

function changelog_count_changes(string $date, int $user): int
{
    $hasDate = mb_strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        CHANGELOG_COUNT_QUERY,
        $hasDate ? 'DATE(`change_created`) = :date' : '1',
        $hasUser ? '`user_id` = :user' : '1'
    );

    $prep = db_prepare($query);

    if ($hasDate) {
        $prep->bindValue('date', $date);
    }

    if ($hasUser) {
        $prep->bindValue('user', $user);
    }

    return $prep->execute() ? (int)$prep->fetchColumn() : 0;
}

function changelog_change_get(int $changeId): array
{
    $getChange = db_prepare('
        SELECT
            c.`change_id`, c.`change_created`, c.`change_log`, c.`change_text`, c.`change_action`,
            u.`user_id`, u.`username`, u.`display_role` AS `user_role`,
            DATE(`change_created`) AS `change_date`,
            COALESCE(u.`user_title`, r.`role_title`) AS `user_title`,
            COALESCE(u.`user_colour`, r.`role_colour`) AS `user_colour`
        FROM `msz_changelog_changes` AS c
        LEFT JOIN `msz_users` AS u
        ON u.`user_id` = c.`user_id`
        LEFT JOIN `msz_roles` AS r
        ON r.`role_id` = u.`display_role`
        WHERE `change_id` = :change_id
    ');
    $getChange->bindValue('change_id', $changeId);
    return db_fetch($getChange);
}

function changelog_change_tags_get(int $changeId): array
{
    $getTags = db_prepare('
        SELECT
            t.`tag_id`, t.`tag_name`, t.`tag_description`
        FROM `msz_changelog_tags` as t
        LEFT JOIN `msz_changelog_change_tags` as ct
        ON ct.`tag_id` = t.`tag_id`
        WHERE ct.`change_id` = :change_id
    ');
    $getTags->bindValue('change_id', $changeId);
    return db_fetch_all($getTags);
}
