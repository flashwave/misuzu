<?php
define('MSZ_TOPIC_TITLE_LENGTH_MIN', 5);
define('MSZ_TOPIC_TITLE_LENGTH_MAX', 100);
define('MSZ_POST_TEXT_LENGTH_MIN', 3);
define('MSZ_POST_TEXT_LENGTH_MAX', 60000);

function forum_validate_title(string $title): string
{
    $length = mb_strlen($title);

    if ($length < MSZ_TOPIC_TITLE_LENGTH_MIN) {
        return 'too-short';
    }

    if ($length > MSZ_TOPIC_TITLE_LENGTH_MAX) {
        return 'too-long';
    }

    return '';
}

function forum_validate_post(string $text): string
{
    $length = mb_strlen($text);

    if ($length < MSZ_POST_TEXT_LENGTH_MIN) {
        return 'too-short';
    }

    if ($length > MSZ_POST_TEXT_LENGTH_MAX) {
        return 'too-long';
    }

    return '';
}
