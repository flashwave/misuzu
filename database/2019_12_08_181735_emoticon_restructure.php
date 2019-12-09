<?php
namespace Misuzu\DatabaseMigrations\EmoticonRestructure;

use PDO;

function migrate_up(PDO $conn): void {
    $emotes = $conn->query('SELECT * FROM `msz_emoticons`')->fetchAll(PDO::FETCH_ASSOC);
    $pruneDupes = $conn->prepare('DELETE FROM `msz_emoticons` WHERE `emote_id` = :id');

    // int order, int hierarchy, string url, array(string string, int order) strings
    $images = [];
    $delete = [];

    foreach($emotes as $emote) {
        if(!isset($images[$emote['emote_url']])) {
            $images[$emote['emote_url']] = [
                'id' => $emote['emote_id'],
                'order' => $emote['emote_order'],
                'hierarchy' => $emote['emote_hierarchy'],
                'url' => $emote['emote_url'],
                'strings' => [],
            ];
        } else {
            $delete[] = $emote['emote_id'];
        }

        $images[$emote['emote_url']]['strings'][] = [
            'string' => $emote['emote_string'],
            'order' => count($images[$emote['emote_url']]['strings']) + 1,
        ];
    }

    foreach($delete as $id) {
        $pruneDupes->bindValue('id', $id);
        $pruneDupes->execute();
    }

    $conn->exec('
        ALTER TABLE `msz_emoticons`
            DROP COLUMN `emote_string`,
            DROP INDEX `emotes_string`,
            DROP INDEX `emotes_url`,
            ADD UNIQUE INDEX `emotes_url` (`emote_url`);
    ');
    $conn->exec("
        CREATE TABLE `msz_emoticons_strings` (
            `emote_id`           INT UNSIGNED NOT NULL,
            `emote_string_order` MEDIUMINT    NOT NULL DEFAULT 0,
            `emote_string`       VARCHAR(50)  NOT NULL COLLATE 'ascii_general_nopad_ci',
                   INDEX `string_emote_foreign` (`emote_id`),
                   INDEX `string_order_key`     (`emote_string_order`),
            UNIQUE INDEX `string_unique`        (`emote_string`),
            CONSTRAINT `string_emote_foreign`
                FOREIGN KEY (`emote_id`)
                REFERENCES `msz_emoticons` (`emote_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin';
    ");

    $insertString = $conn->prepare('
        INSERT INTO `msz_emoticons_strings` (`emote_id`, `emote_string_order`, `emote_string`)
        VALUES (:id, :order, :string)
    ');

    foreach($images as $image) {
        $insertString->bindValue('id', $image['id']);

        foreach($image['strings'] as $string) {
            $insertString->bindValue('order', $string['order']);
            $insertString->bindValue('string', $string['string']);
            $insertString->execute();
        }
    }
}

function migrate_down(PDO $conn): void {
    $conn->exec('DROP TABLE `msz_emoticons_strings`');
    $conn->exec("
        ALTER TABLE `msz_emoticons`
            ADD COLUMN `emote_string` VARCHAR(50) NOT NULL COLLATE 'ascii_general_nopad_ci' AFTER `emote_hierarchy`,
            DROP INDEX `emotes_url`,
            ADD INDEX `emotes_url` (`emote_url`),
            ADD UNIQUE INDEX `emote_string` (`emote_url`);
    ");
}
