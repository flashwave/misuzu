<?php
use Misuzu\Database;

define('MSZ_USER_PROFILE_SET_ERROR', '_err');
define('MSZ_USER_PROFILE_INVALID_FIELD', 1);
define('MSZ_USER_PROFILE_FILTER_FAILED', 2);
define('MSZ_USER_PROFILE_UPDATE_FAILED', 3);
define('MSZ_USER_PROFILE_FIELD_SET_ERRORS', [
    MSZ_USER_PROFILE_INVALID_FIELD,
    MSZ_USER_PROFILE_FILTER_FAILED,
    MSZ_USER_PROFILE_UPDATE_FAILED,
]);

define('MSZ_USER_PROFILE_FIELD_FORMAT', 'user_%s');
define('MSZ_USER_PROFILE_FIELDS', [
    'twitter' => [
        'name' => 'Twitter',
        'regex' => '#^(?:https?://(?:www\.)?twitter.com/(?:\#!\/)?)?@?([A-Za-z0-9_]{1,20})/?$#u',
        'link' => 'https://twitter.com/%s',
        'format' => '@%s',
    ],
    'osu' => [
        'name' => 'osu!',
        'regex' => '#^(?:https?://osu.ppy.sh/u(?:sers)?/)?([a-zA-Z0-9-\[\]_ ]{1,20})/?$#u',
        'link' => 'https://osu.ppy.sh/users/%s',
    ],
    'website' => [
        'name' => 'Website',
        'type' => 'url',
        'regex' => '#^((?:https?)://.{1,240})$#u',
        'link' => '%s',
        'tooltip' => '%s',
    ],
    'youtube' => [
        'name' => 'Youtube',
        'regex' => '#^(?:https?://(?:www.)?youtube.com/(?:(?:user|c|channel)/)?)?(UC[a-zA-Z0-9-_]{1,22}|[a-zA-Z0-9-_%]{1,100})/?$#u',
        'link' => [
            '_' => 'https://youtube.com/%s',
            'UC[a-zA-Z0-9-_]{1,22}' => 'https://youtube.com/channel/%s',
        ],
        'format' => [
            '_' => '%s',
            'UC[a-zA-Z0-9-_]{1,22}' => 'Go to Channel',
        ],
    ],
    'steam' => [
        'name' => 'Steam',
        'regex' => '#^(?:https?://(?:www.)?steamcommunity.com/(?:id|profiles)/)?([a-zA-Z0-9_-]{2,100})/?$#u',
        'link' => 'https://steamcommunity.com/id/%s',
    ],
    'twitchtv' => [
        'name' => 'Twitch.tv',
        'regex' => '#^(?:https?://(?:www.)?twitch.tv/)?([0-9A-Za-z_]{3,25})/?$#u',
        'link' => 'https://twitch.tv/%s',
    ],
    'lastfm' => [
        'name' => 'Last.fm',
        'regex' => '#^(?:https?://(?:www.)?last.fm/user/)?([a-zA-Z]{1}[a-zA-Z0-9_-]{1,14})/?$#u',
        'link' => 'https://www.last.fm/user/%s',
    ],
    'github' => [
        'name' => 'Github',
        'regex' => '#^(?:https?://(?:www.)?github.com/?)?([a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38})/?$#u',
        'link' => 'https://github.com/%s',
    ],
    'skype' => [
        'name' => 'Skype',
        'regex' => '#^((?:live:)?[a-zA-Z][\w\.,\-_@]{1,100})$#u',
        'link' => 'skype:%s?userinfo',
    ],
    'discord' => [
        'name' => 'Discord',
        'regex' => '#^(.{1,32}\#[0-9]{4})$#u',
    ],
]);

function user_profile_field_is_valid(string $name): bool
{
    return array_key_exists($name, MSZ_USER_PROFILE_FIELDS);
}

function user_profile_field_get_display_name(string $name): string
{
    return MSZ_USER_PROFILE_FIELDS[$name]['name'] ?? '';
}

function user_profile_field_get_database_name(string $name): string
{
    return sprintf(MSZ_USER_PROFILE_FIELD_FORMAT, $name);
}

function user_profile_fields_get(): array
{
    return MSZ_USER_PROFILE_FIELDS;
}

function user_profile_field_get_regex(string $name): string
{
    return MSZ_USER_PROFILE_FIELDS[$name]['regex'] ?? '';
}

// === NULL if field is invalid
function user_profile_field_filter(string $name, string $value): ?string
{
    if (!user_profile_field_is_valid($name)) {
        return null;
    }

    $regex = user_profile_field_get_regex($name);

    if (empty($regex) || empty($value)) {
        return $value;
    }

    $checkRegex = preg_match($regex, $value, $matches);

    if (!$checkRegex || empty($matches[1])) {
        return null;
    }

    return $matches[1];
}

function user_profile_fields_set(int $userId, array $fields): array
{
    if (count($fields) < 1) {
        return [];
    }

    $errors = [];
    $values = [];

    foreach ($fields as $name => $value) {
        // should these just be ignored?
        if (!user_profile_field_is_valid($name)) {
            $errors[$name] = MSZ_USER_PROFILE_INVALID_FIELD;
            continue;
        }

        $value = user_profile_field_filter($name, $value);

        if ($value === null) {
            $errors[$name] = MSZ_USER_PROFILE_FILTER_FAILED;
            continue;
        }

        $values[user_profile_field_get_database_name($name)] = $value;
    }

    if (count($values) > 0) {
        $updateFields = Database::prepare('
            UPDATE `msz_users`
            SET ' . pdo_prepare_array_update($values, true) . '
            WHERE `user_id` = :user_id
        ');
        $values['user_id'] = $userId;

        if (!$updateFields->execute($values)) {
            $errors[MSZ_USER_PROFILE_SET_ERROR] = MSZ_USER_PROFILE_UPDATE_FAILED;
        }
    }

    return $errors;
}

function user_profile_fields_display(array $user, bool $hideEmpty = true): array
{
    $output = [];

    foreach (MSZ_USER_PROFILE_FIELDS as $name => $field) {
        $dbn = user_profile_field_get_database_name($name);

        if ($hideEmpty && (!array_key_exists($dbn, $user) || empty($user[$dbn]))) {
            continue;
        }

        $value = $user[$dbn] ?? '';
        $output[$name] = $field;
        $output[$name]['value'] = htmlentities($value);

        foreach (['link', 'format'] as $multipath) {
            if (empty($output[$name][$multipath]) || !is_array($output[$name][$multipath])) {
                continue;
            }

            foreach (array_reverse($output[$name][$multipath], true) as $regex => $string) {
                if ($regex === '_' || !preg_match("#{$regex}#", $value)) {
                    continue;
                }

                $output[$name][$multipath] = $string;
                break;
            }

            if (is_array($output[$name][$multipath])) {
                $output[$name][$multipath] = $output[$name][$multipath]['_'];
            }
        }
    }

    return $output;
}
