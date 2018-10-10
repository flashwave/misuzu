<?php
define('MSZ_CHAT_QUOTES_TAKE', 15);

function chat_quotes_add(
    string $text,
    string $username,
    int $colour,
    ?int $userId = null,
    ?int $parent = null,
    ?int $time = null,
    ?int $quoteId = null
): int {
    if ($quoteId > 0) {
        $insert = db_prepare('
            UPDATE `msz_chat_quotes`
            SET `quote_parent` = :parent,
                `quote_user_id` = :user_id,
                `quote_username` = :username,
                `quote_user_colour` = :user_colour,
                `quote_timestamp` = :time,
                `quote_text` = :text
            WHERE `quote_id` = :id
        ');
        $insert->bindValue('id', $quoteId);
    } else {
        $insert = db_prepare('
            INSERT INTO `msz_chat_quotes` (
                `quote_parent`, `quote_user_id`, `quote_username`,
                `quote_user_colour`, `quote_timestamp`, `quote_text`
            ) VALUES (
                :parent, :user_id, :username, :user_colour, :time, :text
            )
        ');
    }

    $insert->bindValue('parent', $parent < 1 ? null : $parent);
    $insert->bindValue('user_id', $userId < 1 ? null : $userId);
    $insert->bindValue('username', $username);
    $insert->bindValue('user_colour', $colour);
    $insert->bindValue('time', date('Y-m-d H:i:s', $time < 1 ? time() : $time));
    $insert->bindValue('text', $text);

    return $insert->execute() ? db_last_insert_id() : 0;
}

function chat_quotes_count(bool $parentsOnly = false): int
{
    return db_query(sprintf('
        SELECT COUNT(`quote_id`)
        FROM `msz_chat_quotes`
        %s
    ', $parentsOnly ? 'WHERE `quote_parent` IS NULL' : ''))->fetchColumn();
}

function chat_quotes_single(int $quoteId): array
{
    if ($quoteId < 1) {
        return [];
    }

    $getSingle = db_prepare('
        SELECT `quote_id`, `quote_parent`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_id` = :quote
    ');
    $getSingle->bindValue('quote', $quoteId);
    $single = $getSingle->execute() ? $getSingle->fetch(PDO::FETCH_ASSOC) : [];
    return $single ? $single : [];
}

function chat_quotes_parents(int $offset = 0, int $take = MSZ_CHAT_QUOTES_TAKE): array
{
    $getAll = $take < 1 || $offset < 0;

    $getParents = db_prepare(sprintf('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` IS NULL
        ORDER BY `quote_id` DESC
        %s
    ', $getAll ? '' : 'LIMIT :offset, :take'));

    if (!$getAll) {
        $getParents->bindValue('take', $take);
        $getParents->bindValue('offset', $offset);
    }

    $parents = $getParents->execute() ? $getParents->fetchAll() : [];
    return $parents ? $parents : [];
}

function chat_quotes_set(int $parentId): array
{
    $getParent = db_prepare('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` IS NULL
        AND `quote_id` = :parent
    ');
    $getParent->bindValue('parent', $parentId);
    $parent = $getParent->execute() ? $getParent->fetch(PDO::FETCH_ASSOC) : [];
    return $parent ? array_merge([$parent], chat_quotes_children($parent['quote_id'])) : [];
}

function chat_quotes_children(int $parentId): array
{
    $getChildren = db_prepare('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` = :parent
    ');
    $getChildren->bindValue('parent', $parentId);
    $children = $getChildren->execute() ? $getChildren->fetchAll(PDO::FETCH_ASSOC) : [];
    return $children ? $children : [];
}

function chat_quotes_random(): array
{
    $parent = db_query('
        SELECT `quote_id`, `quote_user_id`, `quote_username`, `quote_user_colour`, `quote_timestamp`, `quote_text`
        FROM `msz_chat_quotes`
        WHERE `quote_parent` IS NULL
        ORDER BY RAND()
    ')->fetch(PDO::FETCH_ASSOC);

    return $parent ? array_merge([$parent], chat_quotes_children($parent['quote_id'])) : [];
}
