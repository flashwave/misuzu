<?php
function emotes_list(int $hierarchy = PHP_INT_MAX, bool $unique = false, bool $order = true): array {
    $getEmotes = \Misuzu\DB::prepare('
        SELECT      `emote_id`, `emote_order`, `emote_hierarchy`,
                    `emote_string`, `emote_url`
        FROM        `msz_emoticons`
        WHERE       `emote_hierarchy` <= :hierarchy
        ORDER BY    IF(:order, `emote_order`, `emote_id`)
    ');
    $getEmotes->bind('hierarchy', $hierarchy);
    $getEmotes->bind('order', $order);
    $emotes = $getEmotes->fetchAll();

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

    $getEmote = \Misuzu\DB::prepare('
            SELECT  `emote_id`, `emote_order`, `emote_hierarchy`,
                    `emote_string`, `emote_url`
            FROM    `msz_emoticons`
            WHERE   `emote_id` = :id
    ');
    $getEmote->bind('id', $emoteId);
    return $getEmote->fetch();
}

function emotes_add(string $string, string $url, int $hierarchy = 0, int $order = 0): int {
    if(empty($string) || empty($url)) {
        return -1;
    }

    $insertEmote = \Misuzu\DB::prepare('
        INSERT INTO `msz_emoticons` (
            `emote_order`, `emote_hierarchy`, `emote_string`, `emote_url`
        )
        VALUES (
            :order, :hierarchy, :string, :url
        )
    ');
    $insertEmote->bind('order', $order);
    $insertEmote->bind('hierarchy', $hierarchy);
    $insertEmote->bind('string', $string);
    $insertEmote->bind('url', $url);

    if(!$insertEmote->execute()) {
        return -2;
    }

    return \Misuzu\DB::lastId();
}

function emotes_add_alias(int $emoteId, string $alias): int {
    if($emoteId < 1 || empty($alias)) {
        return -1;
    }

    $createAlias = \Misuzu\DB::prepare('
        INSERT INTO `msz_emoticons` (
            `emote_order`, `emote_hierarchy`, `emote_string`, `emote_url`
        )
        SELECT  `emote_order`, `emote_hierarchy`, :alias, `emote_url`
        FROM    `msz_emoticons`
        WHERE   `emote_id` = :id
    ');
    $createAlias->bind('id', $emoteId);
    $createAlias->bind('alias', $alias);

    if(!$createAlias->execute()) {
        return -2;
    }

    return \Misuzu\DB::lastId();
}

function emotes_update_url(string $existingUrl, string $url, int $hierarchy = 0, int $order = 0): void {
    if(empty($existingUrl) || empty($url)) {
        return;
    }

    $updateByUrl = \Misuzu\DB::prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_url`         = :url,
                `emote_hierarchy`   = :hierarchy,
                `emote_order`       = :order
        WHERE   `emote_url`         = :existing_url
    ');
    $updateByUrl->bind('existing_url', $existingUrl);
    $updateByUrl->bind('url', $url);
    $updateByUrl->bind('hierarchy', $hierarchy);
    $updateByUrl->bind('order', $order);
    $updateByUrl->execute();
}

function emotes_update_string(string $id, string $string): void {
    if($id < 1 || empty($string)) {
        return;
    }

    $updateString = \Misuzu\DB::prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_string` = :string
        WHERE   `emote_id` = :id
    ');
    $updateString->bind('id', $id);
    $updateString->bind('string', $string);
    $updateString->execute();
}

// use this for actually removing emoticons
function emotes_remove_url(string $url): void {
    $removeByUrl = \Misuzu\DB::prepare('
        DELETE FROM `msz_emoticons`
        WHERE `emote_url` = :url
    ');
    $removeByUrl->bind('url', $url);
    $removeByUrl->execute();
}

// use this for removing single aliases
function emotes_remove_id(int $emoteId): void {
    $removeById = \Misuzu\DB::prepare('
        DELETE FROM `msz_emoticons`
        WHERE `emote_id` = :id
    ');
    $removeById->bind('id', $emoteId);
    $removeById->execute();
}

function emotes_order_change(int $id, bool $increase): void {
    $increaseOrder = \Misuzu\DB::prepare('
        UPDATE  `msz_emoticons`
        SET     `emote_order` = IF(:increase, `emote_order` + 1, `emote_order` - 1)
        WHERE   `emote_id` = :id
    ');
    $increaseOrder->bind('id', $id);
    $increaseOrder->bind('increase', $increase ? 1 : 0);
    $increaseOrder->execute();
}
