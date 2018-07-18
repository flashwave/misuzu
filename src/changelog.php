<?php
use Misuzu\Database;

define('MSZ_CHANGELOG_PERM_MANAGE_CHANGES', 1);
define('MSZ_CHANGELOG_PERM_MANAGE_TAGS', 1 << 1);
define('MSZ_CHANGELOG_PERM_MANAGE_ACTIONS', 1 << 2);

function changelog_action_add(string $name, ?int $colour = null, ?string $class = null): int
{
    if ($colour === null) {
        $colour = colour_none();
    }

    $class = preg_replace('#[^a-z]#', '', strtolower($class ?? $name));

    $addAction = Database::prepare('
        INSERT INTO `msz_changelog_actions`
            (`action_name`, `action_colour`, `action_class`)
        VALUES
            (:action_name, :action_colour, :action_class)
    ');
    $addAction->bindValue('action_name', $name);
    $addAction->bindValue('action_colour', $colour);
    $addAction->bindValue('action_class', $class);

    return $addAction->execute() ? (int)Database::lastInsertId() : 0;
}

function changelog_entry_create(int $userId, int $actionId, string $log, string $text = null): int
{
    $createChange = Database::prepare('
        INSERT INTO `msz_changelog_changes`
            (`user_id`, `action_id`, `change_log`, `change_text`)
        VALUES
            (:user_id, :action_id, :change_log, :change_text)
    ');
    $createChange->bindValue('user_id', $userId);
    $createChange->bindValue('action_id', $actionId);
    $createChange->bindValue('change_log', $log);
    $createChange->bindValue('change_text', $text);

    return $createChange->execute() ? (int)Database::lastInsertId() : 0;
}

define('MSZ_CHANGELOG_GET_QUERY', '
    SELECT
        c.`change_id`, c.`change_log`,
        a.`action_name`, a.`action_colour`, a.`action_class`,
        u.`user_id`, u.`username`,
        DATE(`change_created`) as `change_date`,
        !ISNULL(c.`change_text`) as `change_has_text`,
        COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
    FROM `msz_changelog_changes` as c
    LEFT JOIN `msz_users` as u
    ON u.`user_id` = c.`user_id`
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    LEFT JOIN `msz_changelog_actions` as a
    ON a.`action_id` = c.`action_id`
    WHERE %s
    AND %s
    GROUP BY `change_created`, `change_id`
    ORDER BY `change_created` DESC, `change_id` DESC
    %s
');

function changelog_get_changes(string $date, int $user, int $offset, int $take): array
{
    $hasDate = strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        MSZ_CHANGELOG_GET_QUERY,
        $hasDate ? 'DATE(c.`change_created`) = :date' : '1',
        $hasUser ? 'c.`user_id` = :user' : '1',
        !$hasDate ? 'LIMIT :offset, :take' : ''
    );

    $prep = Database::prepare($query);

    if (!$hasDate) {
        $prep->bindValue('offset', $offset);
        $prep->bindValue('take', $take);
    } else {
        $prep->bindValue('date', $date);
    }

    if ($hasUser) {
        $prep->bindValue('user', $user);
    }

    return $prep->execute() ? $prep->fetchAll(PDO::FETCH_ASSOC) : [];
}

define('CHANGELOG_COUNT_QUERY', '
    SELECT COUNT(`change_id`)
    FROM `msz_changelog_changes`
    WHERE %s
    AND %s
');

function changelog_count_changes(string $date, int $user): int
{
    $hasDate = strlen($date) > 0;
    $hasUser = $user > 0;

    $query = sprintf(
        CHANGELOG_COUNT_QUERY,
        $hasDate ? 'DATE(`change_created`) = :date' : '1',
        $hasUser ? '`user_id` = :user' : '1'
    );

    $prep = Database::prepare($query);

    if ($hasDate) {
        $prep->bindValue('date', $date);
    }

    if ($hasUser) {
        $prep->bindValue('user', $user);
    }

    return $prep->execute() ? (int)$prep->fetchColumn() : 0;
}
