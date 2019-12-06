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

function changelog_action_name(int $action): string {
    return changelog_action_is_valid($action) ? MSZ_CHANGELOG_ACTIONS[$action] : '';
}

function changelog_action_is_valid(int $action): bool {
    return array_key_exists($action, MSZ_CHANGELOG_ACTIONS);
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

function changelog_get_changes(string $date, int $user, int $offset, int $take): array {
    $hasDate = mb_strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        MSZ_CHANGELOG_GET_QUERY,
        $hasDate ? 'DATE(c.`change_created`) = :date' : '1',
        $hasUser ? 'c.`user_id` = :user' : '1',
        !$hasDate ? 'LIMIT :offset, :take' : ''
    );

    $prep = \Misuzu\DB::prepare($query);

    if(!$hasDate) {
        $prep->bind('offset', $offset);
        $prep->bind('take', $take);
    } else {
        $prep->bind('date', $date);
    }

    if($hasUser) {
        $prep->bind('user', $user);
    }

    return $prep->fetchAll();
}

define('MSZ_CHANGELOG_COUNT_QUERY', '
    SELECT COUNT(`change_id`)
    FROM `msz_changelog_changes`
    WHERE %s
    AND %s
');

function changelog_count_changes(string $date, int $user): int {
    $hasDate = mb_strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        MSZ_CHANGELOG_COUNT_QUERY,
        $hasDate ? 'DATE(`change_created`) = :date' : '1',
        $hasUser ? '`user_id` = :user' : '1'
    );

    $prep = \Misuzu\DB::prepare($query);

    if($hasDate) {
        $prep->bind('date', $date);
    }

    if($hasUser) {
        $prep->bind('user', $user);
    }

    return (int)$prep->fetchColumn();
}

function changelog_change_get(int $changeId): array {
    $getChange = \Misuzu\DB::prepare('
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
    $getChange->bind('change_id', $changeId);
    return $getChange->fetch();
}

function changelog_change_tags_get(int $changeId): array {
    $getTags = \Misuzu\DB::prepare('
        SELECT
            t.`tag_id`, t.`tag_name`, t.`tag_description`
        FROM `msz_changelog_tags` as t
        LEFT JOIN `msz_changelog_change_tags` as ct
        ON ct.`tag_id` = t.`tag_id`
        WHERE ct.`change_id` = :change_id
    ');
    $getTags->bind('change_id', $changeId);
    return $getTags->fetchAll();
}
