<?php
use Misuzu\Database;

function changelog_action_add(string $name, ?int $colour = null, ?string $class = null): int
{
    $dbc = Database::connection();

    if ($colour === null) {
        $colour = colour_none();
    }

    $class = preg_replace('#[^a-z]#', '', strtolower($class ?? $name));

    $addAction = $dbc->prepare('
        INSERT INTO `msz_changelog_actions`
            (`action_name`, `action_colour`, `action_class`)
        VALUES
            (:action_name, :action_colour, :action_class)
    ');
    $addAction->bindValue('action_name', $name);
    $addAction->bindValue('action_colour', $colour);
    $addAction->bindValue('action_class', $class);

    return $addAction->execute() ? (int)$dbc->lastInsertId() : 0;
}

function changelog_entry_create(int $userId, int $actionId, string $log, string $text = null): int
{
    $dbc = Database::connection();

    $createChange = $dbc->prepare('
        INSERT INTO `msz_changelog_changes`
            (`user_id`, `action_id`, `change_log`, `change_text`)
        VALUES
            (:user_id, :action_id, :change_log, :change_text)
    ');
    $createChange->bindValue('user_id', $userId);
    $createChange->bindValue('action_id', $actionId);
    $createChange->bindValue('change_log', $log);
    $createChange->bindValue('change_text', $text);

    return $createChange->execute() ? (int)$dbc->lastInsertId() : 0;
}
