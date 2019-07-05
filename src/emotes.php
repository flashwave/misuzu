<?php
function emotes_list(int $hierarchy = PHP_INT_MAX, bool $unique = false, bool $order = true): array {
    $getEmotes = db_prepare('
        SELECT      `emote_id`, `emote_order`, `emote_hierarchy`,
                    `emote_string`, `emote_url`
        FROM        `msz_emoticons`
        WHERE       `emote_hierarchy` <= :hierarchy
        ORDER BY    IF(:order, `emote_order`, `emote_id`)
    ');
    $getEmotes->bindValue('hierarchy', $hierarchy);
    $getEmotes->bindValue('order', $order);
    $emotes = db_fetch_all($getEmotes);

    // Removes aliases, emote with lowest ordering is considered the main
    if($unique) {
        $existing = [];

        for($i = 0; $i < count($emotes); $i++) {
            if(in_array($emotes[$i]['emote_url'], $existing)) {
                unset($emotes[$i]);
            } else {
                $existing[] = $emotes[$i]['emote_url'];
            }
        }
    }

    return $emotes;
}

function emotes_get_by_id(int $emoteId): array {
    if($emoteId < 1) {
        return [];
    }

    $getEmote = db_prepare('
            SELECT  `emote_id`, `emote_order`, `emote_hierarchy`,
                    `emote_string`, `emote_url`
            FROM    `msz_emoticons`
            WHERE   `emote_id` = :id
    ');
    $getEmote->bindValue('id', $emoteId);
    return db_fetch($getEmote);
}

function emotes_add(string $string, string $url, int $hierarchy = 0, int $order = 0): int {
    if(empty($string) || empty($url)) {
        return -1;
    }

    $insertEmote = db_prepare('
        INSERT INTO `msz_emoticons` (
            `emote_order`, `emote_hierarchy`, `emote_string`, `emote_url`
        )
        VALUES (
            :order, :hierarchy, :string, :url
        )
    ');
    $insertEmote->bindValue('order', $order);
    $insertEmote->bindValue('hierarchy', $hierarchy);
    $insertEmote->bindValue('string', $string);
    $insertEmote->bindValue('url', $url);

    if(!$insertEmote->execute()) {
        return -2;
    }

    return db_last_insert_id();
}

function emotes_add_alias(int $emoteId, string $alias): int {
    if($emoteId < 1 || empty($alias)) {
        return -1;
    }

    $createAlias = db_prepare('
        INSERT INTO `msz_emoticons` (
            `emote_order`, `emote_hierarchy`, `emote_string`, `emote_url`
        )
        SELECT  `emote_order`, `emote_hierarchy`, :alias, `emote_url`
        FROM    `msz_emoticons`
        WHERE   `emote_id` = :id
    ');
    $createAlias->bindValue('id', $emoteId);
    $createAlias->bindValue('alias', $alias);

    if(!$createAlias->execute()) {
        return -2;
    }

    return db_last_insert_id();
}

function emotes_update_url(string $existingUrl, string $url, int $hierarchy = 0, int $order = 0): void {
    if(empty($existingUrl) || empty($url)) {
        return;
    }

    $updateByUrl = db_prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_url`         = :url,
                `emote_hierarchy`   = :hierarchy,
                `emote_order`       = :order
        WHERE   `emote_url`         = :existing_url
    ');
    $updateByUrl->bindValue('existing_url', $existingUrl);
    $updateByUrl->bindValue('url', $url);
    $updateByUrl->bindValue('hierarchy', $hierarchy);
    $updateByUrl->bindValue('order', $order);
    $updateByUrl->execute();
}

function emotes_update_string(string $id, string $string): void {
    if($id < 1 || empty($string)) {
        return;
    }

    $updateString = db_prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_string` = :string
        WHERE   `emote_id` = :id
    ');
    $updateString->bindValue('id', $id);
    $updateString->bindValue('string', $string);
    $updateString->execute();
}

// use this for actually removing emoticons
function emotes_remove_url(string $url): void {
    $removeByUrl = db_prepare('
        DELETE FROM `msz_emoticons`
        WHERE `emote_url` = :url
    ');
    $removeByUrl->bindValue('url', $url);
    $removeByUrl->execute();
}

// use this for removing single aliases
function emotes_remove_id(int $emoteId): void {
    $removeById = db_prepare('
        DELETE FROM `msz_emoticons`
        WHERE `emote_id` = :id
    ');
    $removeById->bindValue('id', $emoteId);
    $removeById->execute();
}

function emotes_order_change(int $id, bool $increase): void {
    $increaseOrder = db_prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_order` = IF(:increase, `emote_order` + 1, `emote_order` - 1)
        WHERE   `emote_id` = :id
    ');
    $increaseOrder->bindValue('id', $id);
    $increaseOrder->bindValue('increase', $increase ? 1 : 0);
    $increaseOrder->execute();
}
