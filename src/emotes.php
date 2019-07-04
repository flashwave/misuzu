<?php
function emotes_list(int $hierarchy = 0, bool $order = true, bool $refresh = false): array {
    static $emotes = null;

    if($refresh) {
        $emotes = null;
    }

    if(is_null($emotes)) {
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
    }

    return $emotes ?? [];
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

function emotes_add_alias(string $string, string $alias): int {
    if(empty($string) || empty($alias)) {
        return -1;
    }

    $createAlias = db_prepare('
        INSERT INTO `msz_emoticons` (
            `emote_order`, `emote_hierarchy`, `emote_string`, `emote_url`
        )
        SELECT `emote_order`, `emote_hierarchy`, :alias, `emote_url`
        FROM `msz_emoticons`
        WHERE `emote_string` = :string
    ');
    $createAlias->bindValue('string', $string);
    $createAlias->bindValue('alias', $alias);

    if(!$insertEmote->execute()) {
        return -2;
    }

    return db_last_insert_id();
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
function emotes_remove_string(string $string): void {
    $removeByString = db_prepare('
        DELETE FROM `msz_emoticons`
        WHERE `emote_string` = :string
    ');
    $removeByString->bindValue('string', $string);
    $removeByString->execute();
}
