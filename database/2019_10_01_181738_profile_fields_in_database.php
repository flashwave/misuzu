<?php
namespace Misuzu\DatabaseMigrations\ProfileFieldsInDatabase;

use PDO;

function migrate_up(PDO $conn): void {
    $conn->exec("
        CREATE TABLE `msz_profile_fields` (
            `field_id`      INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
            `field_order`   INT(11)             NOT NULL DEFAULT 0,
            `field_key`     VARCHAR(50)         NOT NULL COLLATE 'utf8mb4_general_ci',
            `field_title`   VARCHAR(50)         NOT NULL COLLATE 'utf8mb4_bin',
            `field_regex`   VARCHAR(255)        NOT NULL COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`field_id`),
            UNIQUE INDEX `profile_fields_key_unique` (`field_key`),
            INDEX `profile_fields_order_key` (`field_order`)
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_profile_fields_formats` (
            `format_id`         INT(10) UNSIGNED    NOT NULL    AUTO_INCREMENT,
            `field_id`          INT(10) UNSIGNED    NOT NULL    DEFAULT 0,
            `format_regex`      VARCHAR(255)        NULL        DEFAULT NULL COLLATE 'utf8mb4_bin',
            `format_link`       VARCHAR(255)        NULL        DEFAULT NULL COLLATE 'utf8mb4_bin',
            `format_display`    VARCHAR(255)        NOT NULL    DEFAULT '%s' COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`format_id`),
            INDEX `profile_field_format_field_foreign` (`field_id`),
            CONSTRAINT `profile_field_format_field_foreign`
                FOREIGN KEY (`field_id`)
                REFERENCES `msz_profile_fields` (`field_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $conn->exec("
        CREATE TABLE `msz_profile_fields_values` (
            `field_id`      INT(10) UNSIGNED    NOT NULL,
            `user_id`       INT(10) UNSIGNED    NOT NULL,
            `format_id`     INT(10) UNSIGNED    NOT NULL,
            `field_value`   VARCHAR(255)        NOT NULL    COLLATE 'utf8mb4_bin',
            PRIMARY KEY (`field_id`, `user_id`),
            INDEX `profile_fields_values_format_foreign` (`format_id`),
            INDEX `profile_fields_values_user_foreign` (`user_id`),
            INDEX `profile_fields_values_value_key` (`field_value`),
            CONSTRAINT `profile_fields_values_field_foreign`
                FOREIGN KEY (`field_id`)
                REFERENCES `msz_profile_fields` (`field_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `profile_fields_values_format_foreign`
                FOREIGN KEY (`format_id`)
                REFERENCES `msz_profile_fields_formats` (`format_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE,
            CONSTRAINT `profile_fields_values_user_foreign`
                FOREIGN KEY (`user_id`)
                REFERENCES `msz_users` (`user_id`)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) COLLATE='utf8mb4_bin' ENGINE=InnoDB;
    ");

    $fieldIds = [];
    $fields = [
        ['order' => 10,  'key' => 'website',   'title' => 'Website',         'regex' => '#^((?:https?)://.{1,240})$#u'],
        ['order' => 20,  'key' => 'youtube',   'title' => 'Youtube',         'regex' => '#^(?:https?://(?:www.)?youtube.com/(?:(?:user|c|channel)/)?)?(UC[a-zA-Z0-9-_]{1,22}|[a-zA-Z0-9-_%]{1,100})/?$#u'],
        ['order' => 30,  'key' => 'twitter',   'title' => 'Twitter',         'regex' => '#^(?:https?://(?:www\.)?twitter.com/(?:\#!\/)?)?@?([A-Za-z0-9_]{1,20})/?$#u'],
        ['order' => 40,  'key' => 'ninswitch', 'title' => 'Nintendo Switch', 'regex' => '#^(?:SW-)?([0-9]{4}-[0-9]{4}-[0-9]{4})$#u'],
        ['order' => 50,  'key' => 'twitchtv',  'title' => 'Twitch.tv',       'regex' => '#^(?:https?://(?:www.)?twitch.tv/)?([0-9A-Za-z_]{3,25})/?$#u'],
        ['order' => 60,  'key' => 'steam',     'title' => 'Steam',           'regex' => '#^(?:https?://(?:www.)?steamcommunity.com/(?:id|profiles)/)?([a-zA-Z0-9_-]{2,100})/?$#u'],
        ['order' => 70,  'key' => 'osu',       'title' => 'osu!',            'regex' => '#^(?:https?://osu.ppy.sh/u(?:sers)?/)?([a-zA-Z0-9-\[\]_ ]{1,20})/?$#u'],
        ['order' => 80,  'key' => 'lastfm',    'title' => 'Last.fm',         'regex' => '#^(?:https?://(?:www.)?last.fm/user/)?([a-zA-Z]{1}[a-zA-Z0-9_-]{1,14})/?$#u'],
        ['order' => 90,  'key' => 'github',    'title' => 'Github',          'regex' => '#^(?:https?://(?:www.)?github.com/?)?([a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38})/?$#u'],
        ['order' => 100, 'key' => 'skype',     'title' => 'Skype',           'regex' => '#^((?:live:)?[a-zA-Z][\w\.,\-_@]{1,100})$#u'],
        ['order' => 110, 'key' => 'discord',   'title' => 'Discord',         'regex' => '#^(.{1,32}\#[0-9]{4})$#u'],
    ];
    $formats = [
        ['field' => 'website',   'regex' => null,                      'display' => '%s',            'link' => '%s'],
        ['field' => 'youtube',   'regex' => null,                      'display' => '%s',            'link' => 'https://youtube.com/%s'],
        ['field' => 'youtube',   'regex' => '^UC[a-zA-Z0-9-_]{1,22}$', 'display' => 'Go to Channel', 'link' => 'https://youtube.com/channel/%s'],
        ['field' => 'twitter',   'regex' => null,                      'display' => '@%s',           'link' => 'https://twitter.com/%s'],
        ['field' => 'ninswitch', 'regex' => null,                      'display' => 'SW-%s',         'link' => null],
        ['field' => 'twitchtv',  'regex' => null,                      'display' => '%s',            'link' => 'https://twitch.tv/%s'],
        ['field' => 'steam',     'regex' => null,                      'display' => '%s',            'link' => 'https://steamcommunity.com/id/%s'],
        ['field' => 'osu',       'regex' => null,                      'display' => '%s',            'link' => 'https://osu.ppy.sh/users/%s'],
        ['field' => 'lastfm',    'regex' => null,                      'display' => '%s',            'link' => 'https://www.last.fm/user/%s'],
        ['field' => 'github',    'regex' => null,                      'display' => '%s',            'link' => 'https://github.com/%s'],
        ['field' => 'skype',     'regex' => null,                      'display' => '%s',            'link' => 'skype:%s?userinfo'],
        ['field' => 'discord',   'regex' => null,                      'display' => '%s',            'link' => null],
    ];

    $insertField  = $conn->prepare("INSERT INTO `msz_profile_fields`         (`field_order`, `field_key`,    `field_title`, `field_regex`)    VALUES (:order, :key,   :title,  :regex)");
    $insertFormat = $conn->prepare("INSERT INTO `msz_profile_fields_formats` (`field_id`,    `format_regex`, `format_link`, `format_display`) VALUES (:field, :regex, :link,   :display)");
    $insertValue  = $conn->prepare("INSERT INTO `msz_profile_fields_values`  (`field_id`,    `user_id`,      `format_id`,   `field_value`)    VALUES (:field, :user,  :format, :value)");

    for($i = 0; $i < count($fields); $i++) {
        $insertField->execute($fields[$i]);
        $fields[$i]['id'] = $fieldIds[$fields[$i]['key']] = (int)$conn->lastInsertId();
    }

    for($i = 0; $i < count($formats); $i++) {
        $formats[$i]['field'] = $fieldIds[$formats[$i]['field']];
        $insertFormat->execute($formats[$i]);
        $formats[$i]['id'] = (int)$conn->lastInsertId();
    }

    $users = $conn->query("
        SELECT  `user_id`,      `user_website`, `user_twitter`,     `user_github`,      `user_skype`,   `user_discord`,
                `user_youtube`, `user_steam`,   `user_ninswitch`,   `user_twitchtv`,    `user_osu`,     `user_lastfm`
        FROM `msz_users`
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach($users as $user) {
        foreach($fields as $field) {
            $source = 'user_' . $field['key'];
            $formatId = 0;

            if(empty($user[$source]))
                continue;

            foreach($formats as $format) {
                if($format['field'] != $field['id'])
                    continue;

                if(empty($format['regex']) && $formatId < 1) {
                    $formatId = $format['id'];
                    continue;
                }

                if(preg_match("#{$format['regex']}#", $user[$source])) {
                    $formatId = $format['id'];
                    break;
                }
            }

            $insertValue->execute([
                'field'  => $field['id'],
                'user'   => $user['user_id'],
                'format' => $formatId,
                'value'  => $user[$source],
            ]);
        }
    }

    $conn->exec("
        ALTER TABLE `msz_users`
            DROP COLUMN `user_website`,
            DROP COLUMN `user_twitter`,
            DROP COLUMN `user_github`,
            DROP COLUMN `user_skype`,
            DROP COLUMN `user_discord`,
            DROP COLUMN `user_youtube`,
            DROP COLUMN `user_steam`,
            DROP COLUMN `user_ninswitch`,
            DROP COLUMN `user_twitchtv`,
            DROP COLUMN `user_osu`,
            DROP COLUMN `user_lastfm`;
    ");
}

function migrate_down(PDO $conn): void {
    $conn->exec("
        ALTER TABLE `msz_users`
            ADD COLUMN `user_website`   VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_background_settings`,
            ADD COLUMN `user_twitter`   VARCHAR(20)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_website`,
            ADD COLUMN `user_github`    VARCHAR(40)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_twitter`,
            ADD COLUMN `user_skype`     VARCHAR(60)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_github`,
            ADD COLUMN `user_discord`   VARCHAR(40)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_skype`,
            ADD COLUMN `user_youtube`   VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_discord`,
            ADD COLUMN `user_steam`     VARCHAR(255) NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_youtube`,
            ADD COLUMN `user_ninswitch` VARCHAR(14)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_steam`,
            ADD COLUMN `user_twitchtv`  VARCHAR(30)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_ninswitch`,
            ADD COLUMN `user_osu`       VARCHAR(20)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_twitchtv`,
            ADD COLUMN `user_lastfm`    VARCHAR(20)  NOT NULL DEFAULT '' COLLATE 'utf8mb4_bin' AFTER `user_osu`;
    ");

    $existingFields = $conn->query("
        SELECT      pfv.`user_id`, pf.`field_key`, pfv.`field_value`
        FROM        `msz_profile_fields_values` AS pfv
        LEFT JOIN   `msz_profile_fields` AS pf
        ON          pf.`field_id` = pfv.`field_id`
    ");

    $updatePreps = [];
    foreach($existingFields as $field) {
        ($updatePreps[$field['field_key']] ?? ($updatePreps[$field['field_key']] = $conn->prepare("UPDATE `msz_users` SET `user_{$field['field_key']}` = :value WHERE `user_id` = :user_id")))->execute([
            'value' => $field['field_value'],
            'user_id' => $field['user_id'],
        ]);
    }

    $conn->exec("DROP TABLE `msz_profile_fields_values`");
    $conn->exec("DROP TABLE `msz_profile_fields_formats`");
    $conn->exec("DROP TABLE `msz_profile_fields`");
}
