<?php
function chat_quotes_add(
    string $text,
    string $username,
    int $colour,
    ?int $userId = null,
    ?int $parent = null,
    ?int $time = null
): int {
    $insert = db_prepare('
        INSERT INTO `msz_chat_quotes` (
            `quote_parent`, `quote_user_id`, `quote_username`,
            `quote_user_colour`, `quote_timestamp`, `quote_text`
        ) VALUES (
            :parent, :user_id, :username, :user_colour, :time, :text
        )
    ');

    $insert->bindValue('parent', $parent);
    $insert->bindValue('user_id', $userId);
    $insert->bindValue('username', $username);
    $insert->bindValue('user_colour', $colour);
    $insert->bindValue('time', date('Y-m-d H:i:s', $time ?? time()));
    $insert->bindValue('text', $text);

    return $insert->execute() ? db_last_insert_id() : 0;
}

function chat_quotes_random(): array
{
    $quotes = [];

    $parent = db_query('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` IS NULL
        ORDER BY RAND()
    ')->fetch(PDO::FETCH_ASSOC);

    if (!$parent) {
        return [];
    }

    $quotes[] = $parent;

    $getChildren = db_prepare('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` = :parent
    ');
    $getChildren->bindValue('parent', $parent['quote_id']);
    $children = $getChildren->execute() ? $getChildren->fetchAll(PDO::FETCH_ASSOC) : [];

    if ($children) {
        $quotes = array_merge($quotes, $children);
    }

    usort($quotes, function ($rowA, $rowB) {
        return strcmp($rowA['quote_timestamp'], $rowB['quote_timestamp']);
    });

    return $quotes;
}
